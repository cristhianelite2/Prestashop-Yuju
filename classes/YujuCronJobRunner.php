<?php
/**
 * Ejecución asíncrona de crons desde el BO (sin bloquear sesión PHP).
 */
class YujuCronJobRunner
{
    const STATUS_QUEUED = 'queued';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_ERROR = 'error';

    /** @var string */
    private $jobsDir;

    /** @var string */
    private $cronDir;

    public function __construct()
    {
        $moduleRoot = dirname(__FILE__) . '/..';
        $this->cronDir = $moduleRoot . '/cron';
        $this->jobsDir = $moduleRoot . '/cache/cron_jobs';
        if (!is_dir($this->jobsDir)) {
            @mkdir($this->jobsDir, 0755, true);
        }
    }

    /**
     * @return string
     */
    public function getJobsDir()
    {
        return $this->jobsDir;
    }

    /**
     * Valida nombre de script bajo cron/.
     *
     * @param string $script
     *
     * @return bool
     */
    public function isAllowedScript($script)
    {
        $script = (string) $script;
        if ($script === '' || !preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $script)) {
            return false;
        }
        $path = $this->cronDir . '/' . $script;
        if (!is_file($path)) {
            return false;
        }
        // No permitir ejecutar el wrapper de jobs como script "usuario"
        if ($script === 'run_cron_job.php') {
            return false;
        }

        return true;
    }

    /**
     * Crea un job y lanza el worker en background.
     *
     * @param string $script
     * @param string $phpBin
     *
     * @return array{success:bool,job_id?:string,message?:string}
     */
    public function start($script, $phpBin)
    {
        $script = basename((string) $script);
        if (!$this->isAllowedScript($script)) {
            return ['success' => false, 'message' => 'Cron no válido.'];
        }

        $phpBin = trim((string) $phpBin);
        if ($phpBin === '') {
            return ['success' => false, 'message' => 'Binario PHP CLI no disponible.'];
        }

        $jobId = date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $job = [
            'id' => $jobId,
            'script' => $script,
            'status' => self::STATUS_QUEUED,
            'created_at' => date('Y-m-d H:i:s'),
            'started_at' => null,
            'finished_at' => null,
            'exit_code' => null,
            'duration_ms' => null,
            'output' => '',
            'pid' => null,
            'message' => 'En cola para ejecución en segundo plano…',
        ];

        if (!$this->writeJob($jobId, $job)) {
            return ['success' => false, 'message' => 'No se pudo crear el archivo de job.'];
        }

        $wrapper = $this->cronDir . '/run_cron_job.php';
        if (!is_file($wrapper)) {
            $job['status'] = self::STATUS_ERROR;
            $job['message'] = 'Falta cron/run_cron_job.php';
            $job['finished_at'] = date('Y-m-d H:i:s');
            $this->writeJob($jobId, $job);

            return ['success' => false, 'message' => $job['message'], 'job_id' => $jobId];
        }

        $cmd = escapeshellarg($phpBin)
            . ' -d date.timezone=America/Bogota '
            . escapeshellarg($wrapper)
            . ' --job=' . escapeshellarg($jobId)
            . ' --script=' . escapeshellarg($script);

        $spawned = $this->spawnDetached($cmd);
        if (!$spawned) {
            $job['status'] = self::STATUS_ERROR;
            $job['message'] = 'No se pudo lanzar el proceso en segundo plano.';
            $job['finished_at'] = date('Y-m-d H:i:s');
            $this->writeJob($jobId, $job);

            return ['success' => false, 'message' => $job['message'], 'job_id' => $jobId];
        }

        return [
            'success' => true,
            'job_id' => $jobId,
            'message' => 'Cron lanzado en segundo plano. Puede seguir navegando el backoffice.',
        ];
    }

    /**
     * @param string $jobId
     *
     * @return array<string,mixed>|null
     */
    public function getJob($jobId)
    {
        $jobId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $jobId);
        if ($jobId === '') {
            return null;
        }
        $path = $this->jobPath($jobId);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Marca job en ejecución (llamado desde el wrapper CLI).
     *
     * @param string $jobId
     *
     * @return bool
     */
    public function markRunning($jobId)
    {
        $job = $this->getJob($jobId);
        if (!$job) {
            return false;
        }
        $job['status'] = self::STATUS_RUNNING;
        $job['started_at'] = date('Y-m-d H:i:s');
        $job['message'] = 'Ejecutando…';
        $job['pid'] = function_exists('getmypid') ? getmypid() : null;

        return $this->writeJob($jobId, $job);
    }

    /**
     * Finaliza job con salida (wrapper CLI).
     *
     * @param string $jobId
     * @param int $exitCode
     * @param string $output
     * @param int $durationMs
     *
     * @return bool
     */
    public function markFinished($jobId, $exitCode, $output, $durationMs)
    {
        $job = $this->getJob($jobId);
        if (!$job) {
            return false;
        }
        $exitCode = (int) $exitCode;
        $ok = $exitCode === 0;
        $job['status'] = $ok ? self::STATUS_SUCCESS : self::STATUS_ERROR;
        $job['exit_code'] = $exitCode;
        $job['duration_ms'] = (int) $durationMs;
        $job['finished_at'] = date('Y-m-d H:i:s');
        $job['output'] = (string) $output;
        $job['message'] = $ok
            ? 'Cron ejecutado correctamente.'
            : ('El cron terminó con error (exit code ' . $exitCode . ').');

        $written = $this->writeJob($jobId, $job);
        $this->updateRunsRegistry(
            isset($job['script']) ? (string) $job['script'] : '',
            $job['status'],
            (int) $durationMs,
            (string) $output
        );

        return $written;
    }

    /**
     * @param string $command
     *
     * @return bool
     */
    private function spawnDetached($command)
    {
        $command = trim((string) $command);
        if ($command === '') {
            return false;
        }

        if (stripos(PHP_OS, 'WIN') === 0) {
            // Windows / XAMPP: no bloquear el request del BO
            $full = 'start /B "" ' . $command;
            $handle = @popen($full, 'r');
            if (!is_resource($handle)) {
                return false;
            }
            @pclose($handle);

            return true;
        }

        // Linux / producción
        $full = $command . ' > /dev/null 2>&1 &';
        @exec($full);

        return true;
    }

    /**
     * @param string $jobId
     *
     * @return string
     */
    private function jobPath($jobId)
    {
        return $this->jobsDir . '/' . $jobId . '.json';
    }

    /**
     * @param string $jobId
     * @param array $job
     *
     * @return bool
     */
    private function writeJob($jobId, array $job)
    {
        $path = $this->jobPath($jobId);
        $json = json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return false;
        }
        $ok = @file_put_contents($path, $json, LOCK_EX);

        return $ok !== false;
    }

    /**
     * Actualiza el registro usado por el listado del modal.
     *
     * @param string $script
     * @param string $status
     * @param int $durationMs
     * @param string $output
     *
     * @return void
     */
    private function updateRunsRegistry($script, $status, $durationMs, $output)
    {
        if ($script === '') {
            return;
        }
        $path = dirname(__FILE__) . '/../cache/yuju_cron_runs.json';
        $all = [];
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded)) {
                $all = $decoded;
            }
        }
        $all[$script] = [
            'ran_at' => date('Y-m-d H:i:s'),
            'status' => (string) $status,
            'duration_ms' => (int) $durationMs,
            'output_excerpt' => function_exists('mb_substr')
                ? mb_substr((string) $output, 0, 8000)
                : substr((string) $output, 0, 8000),
        ];
        @file_put_contents($path, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}

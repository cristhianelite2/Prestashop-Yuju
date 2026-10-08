#!/usr/bin/env python3
"""
Script para probar el endpoint auth-generate-token de Yuju
Uso: python3 test_yuju_auth.py <client_id> <secret_key> <code>
"""

import sys
import json
import requests


def get_token(client_id: str, secret_key: str, code: str) -> dict:
    """Obtiene el access token de Yuju."""
    url = "https://api.tp.yuju.io/auth-generate-token"
    
    payload = {
        "client_id": client_id,
        "secret_key": secret_key,
        "code": code,
    }
    
    headers = {
        "Content-Type": "application/json",
        "Accept": "application/json",
    }
    
    try:
        response = requests.post(url, json=payload, headers=headers, timeout=30)
        
        result = {
            "status_code": response.status_code,
            "headers": dict(response.headers),
        }
        
        try:
            result["body"] = response.json()
        except json.JSONDecodeError:
            result["body"] = response.text
        
        return result
        
    except requests.exceptions.RequestException as e:
        return {
            "status_code": None,
            "error": str(e),
            "body": None,
        }


def main():
    if len(sys.argv) != 4:
        print("Uso: python3 test_yuju_auth.py <client_id> <secret_key> <code>")
        print()
        print("Ejemplo:")
        print('  python3 test_yuju_auth.py "2c46b2fef0d3aae82893ab0c958cf343" "3oStgk4QG6PZZC90t-QMZ4KScavPkAUQXv6C4gEeiqo" "d4a5399ba3ab999570045ef82aab5d60e2da184ec177210f7ff9ea2add3e359d"')
        sys.exit(1)
    
    client_id = sys.argv[1]
    secret_key = sys.argv[2]
    code = sys.argv[3]
    
    print(f"Probando auth-generate-token...")
    print(f"  client_id: {client_id[:10]}...")
    print(f"  secret_key: {secret_key[:10]}...")
    print(f"  code: {code[:20]}...")
    print()
    
    result = get_token(client_id, secret_key, code)
    
    print(f"Status: {result['status_code']}")
    
    if result.get("error"):
        print(f"Error de conexión: {result['error']}")
        sys.exit(1)
    
    body = result.get("body", {})
    
    if isinstance(body, dict):
        print(f"Respuesta: {json.dumps(body, indent=2, ensure_ascii=False)}")
        
        if result["status_code"] == 200 and "token" in body:
            print()
            print("✅ ÉXITO - Token obtenido:")
            print(f"   {body['token']}")
            print()
            print("Para usar en API calls:")
            print(f"  Authorization: {body['token']}")
        elif result["status_code"] == 403:
            print()
            print("❌ ERROR 403 - Credenciales inválidas o code expirado")
            print("   Verifica:")
            print("   1. client_id y secret_key correctos en Yuju > Aplicaciones > Ver credenciales")
            print("   2. El code es NUEVO (generado al hacer clic en 'Conectar' en Yuju)")
            print("   3. Redirect URL en la app de Yuju coincide EXACTAMENTE")
        else:
            print()
            print(f"❌ ERROR {result['status_code']}")
    else:
        print(f"Respuesta cruda: {body}")


if __name__ == "__main__":
    main()
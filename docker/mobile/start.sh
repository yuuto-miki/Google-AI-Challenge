#!/bin/sh
# Run from WSL. Trust only the public CA certificate on the phone.
set -eu
cd "$(dirname "$0")/../.."
WALK_LAN_IP=$(python3 -c 'import ipaddress, sys; ip = ipaddress.IPv4Address(sys.argv[1]); assert ip.is_private and not ip.is_loopback; print(ip)' "${1:?Supply the Wi-Fi IPv4 address}")
export WALK_LAN_IP
tls=docker/mobile/tls
public_ca=src/public/walk/mobile-ca.cer
mkdir -p "$tls"
if ! { test -s "$tls/server.key" && test -s "$public_ca" &&
       openssl x509 -in "$tls/server.crt" -checkend 86400 -noout >/dev/null 2>&1 &&
       openssl x509 -in "$tls/server.crt" -ext subjectAltName | grep -Fxq "    IP Address:$WALK_LAN_IP"; }; then
    umask 077
    tmp=$(mktemp -d)
    trap 'rm -f "$tmp/ca.key" "$tmp/ca.crt" "$tmp/leaf.csr" "$tmp/leaf.ext"; rmdir "$tmp"' EXIT
    openssl req -x509 -newkey rsa:2048 -nodes -days 366 -sha256 -subj '/CN=WALK QUEST local Wi-Fi CA' \
        -addext 'basicConstraints=critical,CA:TRUE,pathlen:0' -addext 'keyUsage=critical,keyCertSign' \
        -keyout "$tmp/ca.key" -out "$tmp/ca.crt"
    openssl req -new -newkey rsa:2048 -nodes -subj "/CN=$WALK_LAN_IP" -keyout "$tls/server.key" -out "$tmp/leaf.csr"
    printf '%s\n' "subjectAltName=IP:$WALK_LAN_IP" 'basicConstraints=critical,CA:FALSE' 'extendedKeyUsage=serverAuth' 'keyUsage=critical,digitalSignature,keyEncipherment' > "$tmp/leaf.ext"
    openssl x509 -req -in "$tmp/leaf.csr" -CA "$tmp/ca.crt" -CAkey "$tmp/ca.key" -set_serial "0x$(openssl rand -hex 16)" \
        -days 365 -sha256 -extfile "$tmp/leaf.ext" -out "$tls/server.crt"
    openssl x509 -in "$tmp/ca.crt" -outform DER -out "$public_ca"
    chmod 644 "$public_ca" "$tls/server.crt"
fi
docker compose -f docker-compose.yml -f compose.mobile.yml up -d --no-deps --force-recreate mobile
openssl x509 -inform DER -in "$public_ca" -noout -fingerprint -sha256
printf 'https://%s:8443/\n' "$WALK_LAN_IP"

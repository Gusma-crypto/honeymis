#!/bin/sh

set -eu
umask 077

cd "$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"

fail() {
    printf '\nDeploy gagal: %s\n' "$*" >&2
    exit 1
}

usage() {
    cat <<'EOF'
Pemakaian:
  sh deploy.sh                             Install/update demo dari kode saat ini
  sh deploy.sh --url http://IP_SERVER:8000  Set alamat demo
  sh deploy.sh --update                     Git pull --ff-only, build, deploy

Memerlukan Docker Engine, Docker Compose v2, dan OpenSSL.
Hanya untuk demo APP_ENV=local; tidak menghapus database atau mengganti key lama.
Password akun demo tersimpan dalam DEMO_PASSWORD di .env.docker.
EOF
}

update=false
demo_url=
while [ "$#" -gt 0 ]; do
    case "$1" in
        --update) update=true; shift ;;
        --url)
            [ "$#" -ge 2 ] || fail '--url memerlukan alamat HTTP/HTTPS.'
            demo_url=$2
            shift 2
            ;;
        -h|--help) usage; exit 0 ;;
        *) usage >&2; fail "Opsi tidak dikenal: $1" ;;
    esac
done

if [ -n "$demo_url" ]; then
    printf '%s\n' "$demo_url" | grep -Eq '^https?://[A-Za-z0-9][A-Za-z0-9.:-]*(/[A-Za-z0-9/_-]*)?$' \
        || fail 'Gunakan URL sederhana seperti http://IP_SERVER:8000 atau https://demo.example.com.'
fi

command -v docker >/dev/null 2>&1 || fail 'Pasang Docker Engine terlebih dahulu.'
docker compose version >/dev/null 2>&1 || fail 'Pasang Docker Compose plugin v2 terlebih dahulu.'
docker info >/dev/null 2>&1 || fail 'Docker daemon belum berjalan atau akun ini belum memiliki akses Docker.'
command -v openssl >/dev/null 2>&1 || fail 'Pasang OpenSSL: sudo apt-get install openssl'

compose() {
    docker compose --env-file .env.docker "$@"
}

read_env() {
    awk -v key="$1" '
        index($0, key "=") == 1 {
            value = substr($0, length(key) + 2)
            sub(/\r$/, "", value)
            if (value ~ /^".*"$/ || value ~ /^\047.*\047$/) {
                value = substr(value, 2, length(value) - 2)
            }
            result = value
        }
        END { printf "%s", result }
    ' .env.docker
}

temporary_env=
cleanup() {
    if [ -n "$temporary_env" ]; then
        rm -f -- "$temporary_env"
    fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

set_env() {
    temporary_env=$(mktemp .env.docker.tmp.XXXXXX)
    ENV_VALUE=$2 awk -v key="$1" '
        index($0, key "=") == 1 {
            if (! found) { print key "=" ENVIRON["ENV_VALUE"] }
            found = 1
            next
        }
        { sub(/\r$/, ""); print }
        END { if (! found) { print key "=" ENVIRON["ENV_VALUE"] } }
    ' .env.docker > "$temporary_env"
    mv -- "$temporary_env" .env.docker
    temporary_env=
}

if [ -f .env.docker ]; then
    [ "$(read_env APP_ENV)" = local ] || fail 'Environment yang ada bukan local. Script ini khusus demo, bukan produksi.'
    [ "$(read_env APP_SEED_DEMO)" = true ] || fail 'Set APP_SEED_DEMO=true di .env.docker khusus demo terlebih dahulu.'
    for variable in DB_PASSWORD MYSQL_ROOT_PASSWORD DEMO_PASSWORD; do
        [ -n "$(read_env "$variable")" ] || fail "$variable kosong pada instalasi lama. Isi manual; password lama tidak akan diganti otomatis."
    done
fi

if [ "$update" = true ]; then
    command -v git >/dev/null 2>&1 || fail 'Git diperlukan untuk --update.'
    git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail 'Direktori ini bukan checkout Git.'
    git diff --quiet && git diff --cached --quiet || fail 'Commit atau simpan perubahan kode sebelum --update.'
    git pull --ff-only
fi

if [ ! -f .env.docker ]; then
    printf '\nMenyiapkan konfigurasi demo baru...\n'
    cp .env.docker.example .env.docker
    application_key=$(openssl rand -base64 32)
    database_password=$(openssl rand -hex 24)
    root_password=$(openssl rand -hex 24)
    demo_password=$(openssl rand -hex 12)
    set_env APP_KEY "base64:$application_key"
    set_env DB_PASSWORD "$database_password"
    set_env MYSQL_ROOT_PASSWORD "$root_password"
    set_env DEMO_PASSWORD "$demo_password"
    set_env DOCKER_HTTP_BIND 0.0.0.0
    set_env DEMO_ALLOW_REMOTE true
fi
chmod 600 .env.docker

if [ -n "$demo_url" ]; then
    set_env APP_URL "$demo_url"
    set_env DEMO_ALLOW_REMOTE true
    case "$demo_url" in
        https://*) set_env SESSION_SECURE_COOKIE true ;;
        http://*) set_env SESSION_SECURE_COOKIE false ;;
    esac
fi

compose config --quiet
printf '\nMembangun backend dan frontend dari kode terbaru...\n'
compose build app web

printf '\nMenghentikan proses aplikasi lama sebelum memperbarui database...\n'
compose stop web worker scheduler app

printf '\nMenyiapkan MySQL, izin trigger, dan Redis...\n'
if ! compose up -d --wait --wait-timeout 180 mysql redis; then
    compose logs --tail=100 mysql redis >&2 || true
    fail 'MySQL/Redis belum siap. Periksa log, kredensial database lama, dan command --log-bin-trust-function-creators=1 pada service mysql.'
fi

printf '\nMenjalankan migration, seed demo, dan aplikasi...\n'
if ! compose up -d --no-build --force-recreate --wait --wait-timeout 300 app web worker scheduler; then
    compose logs --tail=100 app web worker scheduler >&2 || true
    fail 'Periksa log di atas. Data tetap disimpan pada volume Docker; jangan jalankan down -v.'
fi

compose exec -T app php artisan honey:reconcile-finance --no-interaction
compose exec -T web wget -q -O /dev/null http://127.0.0.1/up
compose ps

printf '\nDeploy demo selesai.\n'
printf 'APP_URL: %s\n' "$(read_env APP_URL)"
printf 'Port host: %s (bind %s)\n' "$(read_env DOCKER_HTTP_PORT)" "$(read_env DOCKER_HTTP_BIND)"
printf 'Administrator: admin@honey-demo.test\nFinance: finance@honey-demo.test\n'
printf 'Mitra utama: partner@honey-demo.test\nMitra lain: partner2@honey-demo.test sampai partner10@honey-demo.test\n'
printf 'Pembeli: customer@honey-demo.test\n'
printf 'Password: lihat DEMO_PASSWORD di .env.docker (tidak dicetak ke log).\n'
printf 'Jaringan: 10 mitra, kedalaman 5 generasi, rank Member/Bronze/Silver/Gold/Platinum.\n'
printf 'Untuk update kode dari Git: sh deploy.sh --update\n'

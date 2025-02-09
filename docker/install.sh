#!/usr/bin/env bash

set -e

readonly DOCKER_PATH=$(dirname $(realpath $0))
cd ${DOCKER_PATH};

. ./lib/functions.sh

parse_env ".env.dist" ".env"
. ./.env
echo -e "${GREEN}Configuration done!${RESET}" > /dev/tty

block_info "Build Docker"
docker compose build --parallel

block_info "Install dependencies"
./exec composer install
mkdir -p ./../.cache/
echo -e "${GREEN}Dependencies installed with success!${RESET}" > /dev/tty

block_success "Installation completed!"

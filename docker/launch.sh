#!/bin/bash
# Script to launch PrestaShop plugins for testing in various PS versions.
# if any variable is unset exits with error
set -u

# exists at first non 0 exit code
set -e

# Usage info
show_help() {
cat << EOF
Usage: ${0##*/} [-h] [-v VER] [-o OUT_DIR]...
Build Pixelcrush PrestaShop plugin

    -h           display this help and exit
    -v VER       PrestaShop version, 1.5, 1.6 or 1.7
    -d DOMAIN    PrestaShop domain for testing, localhost by default

EOF
}

# A POSIX variable
OPTIND=1         # Reset in case getopts has been used previously in the shell.

command -v unzip >/dev/null 2>&1 || { echo >&2 "You need the unzip binary in the path"; exit 1; }
command -v envsubst >/dev/null 2>&1 || { echo >&2 "You need the envsubst binary in the path"; exit 1; }
command -v docker-compose >/dev/null 2>&1 || { echo >&2 "You need the docker-compose binary in the path"; exit 1; }

export PS_VERSION=""
export PS_DOMAIN="localhost"
cur_dir=$(pwd)
script_dir="$( cd "$(dirname "$0")" ; pwd -P )"

while getopts "h?v:d:" opt; do
    case "$opt" in
        h|\?)
            show_help
            exit 0
            ;;
        v)  PS_VERSION=$OPTARG
            ;;
        d)  PS_DOMAIN=$OPTARG
            ;;
    esac
done

# Basic checks
if [ "${PS_VERSION}" = "" ]; then
  echo "You need to define a specific PrestaShop version, check help with -h"
  exit 1
fi

export DEBUG_GROUP_ID=$(id -g)
export OUT_DEBUG_FOLDER="ps${PS_VERSION}"

# cd into script directory
cd "${script_dir}"

# Create env file for Docker Compose.
if [ ! -e .env  ]; then
  cp php-env.template .env
fi

export MYSQL_VERSION=""
export PS_DOCKER_VERSION=""
# Per version files
if [ "${PS_VERSION}" = "1.5" ]; then
  MYSQL_VERSION="5.5"
  PS_DOCKER_VERSION="1.5.6.3"
elif [ "${PS_VERSION}" = "1.6" ]; then
  MYSQL_VERSION="5.6"
  PS_DOCKER_VERSION="1.6.1.17"
elif [ "${PS_VERSION}" = "1.7" ]; then
  MYSQL_VERSION="5.7"
  PS_DOCKER_VERSION="1.7.2.4"
else
  echo "Unsupported PrestaShop version: ${PS_VERSION}"
  exit 1
fi

out_module_dir="${script_dir}/${OUT_DEBUG_FOLDER}/module"

if [ -e "${out_module_dir}" ]; then
  rm -R "${out_module_dir}"
fi

mkdir -p "${script_dir}/${OUT_DEBUG_FOLDER}/root"
mkdir -p "${out_module_dir}"

../build_plugin.sh -v ${PS_VERSION} -o "${out_module_dir}" > /dev/null
# If something bad happened inside build script
if [ $? -ne 0 ]; then
  exit 1
fi
unzip -u -o -qq "${out_module_dir}/pixelcrush-${PS_VERSION}.zip" -d "${out_module_dir}"
rm "${out_module_dir}/pixelcrush-${PS_VERSION}.zip"

# Creating the required version Dockerfile
envsubst < Dockerfile.template > Dockerfile

echo "Cleaning old resources and starting compose..."
find ./ -type l -name cache -exec rm {} \;
# Check if new images are ready to be downloaded.
docker-compose pull --parallel
# Remove existing network and data volumes related to this compose
docker-compose down -v --remove-orphans
# Starts compose in foreground recreating, and building, the containers
docker-compose up --force-recreate --build

# Backup, just in case you shoot yourself in the foot
backup_dir="${script_dir}/${OUT_DEBUG_FOLDER}/backup"
echo "Backing up to ${backup_dir}"
mkdir -p "${backup_dir}"
cp -r "${script_dir}/${OUT_DEBUG_FOLDER}/root/modules/pixelcrush" "${backup_dir}"
cp "${script_dir}/${OUT_DEBUG_FOLDER}/root/override/classes/Link.php" "${backup_dir}"
cp "${script_dir}/${OUT_DEBUG_FOLDER}/root/override/classes/Media.php" "${backup_dir}"


# Go back to previous working dir.
cd ${cur_dir}

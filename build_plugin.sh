#!/usr/bin/env bash
# Script to build PrestaShop module for specific versions.

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
    -o OUT_DIR   output directory where store the zip file will be built, cur_dir by default

EOF
}

# A POSIX variable
OPTIND=1         # Reset in case getopts has been used previously in the shell.

ps_version=""
tmp_dir=$(mktemp -d)
prev_dir=$(pwd)
out_dir=${prev_dir}


while getopts "h?v:o:" opt; do
    case "$opt" in
        h|\?)
            show_help
            exit 0
            ;;
        v)  ps_version=$OPTARG
            ;;
        o)  out_dir=$OPTARG
            ;;
    esac
done

# Basic checks
if [ "${ps_version}" = "" ]
  then
    echo "You need to define a specific PrestaShop version, check help with -h"
    exit 1
elif [ ! -d "${out_dir}" ]
  then
    echo "The output directory needs to exist before launching this script"
    exit 1
fi

command -v zip >/dev/null 2>&1 || { echo >&2 "You need the zip binary in the path"; exit 1; }

# Shared files
cp -a pixelcrush "${tmp_dir}"

# Per version files
if [ "${ps_version}" = "1.5" ]
  then
    cp -a 1.5/* "${tmp_dir}/pixelcrush"
    sed -e "s/\$this->ps_versions_compliancy.*/\$this->ps_versions_compliancy = array\(\'min\' => \'1.5\', \'max\' => \'1.5.9.9\'\);/g" \
        "${tmp_dir}/pixelcrush/pixelcrush.php" > "${tmp_dir}/pixelcrush/pixelcrush.php.new"

    mv "${tmp_dir}/pixelcrush/pixelcrush.php.new" "${tmp_dir}/pixelcrush/pixelcrush.php"
    out_fname="${out_dir}/pixelcrush-1.5.zip"

elif [ "${ps_version}" = "1.6" ]
  then
    cp -a 1.6/* "${tmp_dir}/pixelcrush"
    sed -e "s/\$this->ps_versions_compliancy.*/\$this->ps_versions_compliancy = array\(\'min\' => \'1.6\', \'max\' => \'1.6.9.9\'\);/g" \
        "${tmp_dir}/pixelcrush/pixelcrush.php" > "${tmp_dir}/pixelcrush/pixelcrush.php.new"
    
    mv "${tmp_dir}/pixelcrush/pixelcrush.php.new" "${tmp_dir}/pixelcrush/pixelcrush.php"
    out_fname="${out_dir}/pixelcrush-1.6.zip"
elif [ "${ps_version}" = "1.7" ]
  then
    cp -a 1.7/* "${tmp_dir}/pixelcrush"
    out_fname="${out_dir}/pixelcrush-1.7.zip"
else
  echo "Unsupport PrestaShop version: ${ps_version}"
  exit 1
fi

# Compress results
cd "${tmp_dir}"
zip -r --exclude=*.DS_Store* "$out_fname" pixelcrush > /dev/null
cd "${prev_dir}"

# Remove temp directory
rm -R "${tmp_dir}"

echo "Module built in ${out_fname}"

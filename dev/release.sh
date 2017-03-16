#!/usr/bin/env bash

ABSOLUTE_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/"
cd "$ABSOLUTE_PATH..";

zip -r -9 "release.zip" "." -x ".git*" ".*" "dev*" "gifs*" "CONTRIBUTING.txt" "*.DS_Store" "*__MACOSX*"
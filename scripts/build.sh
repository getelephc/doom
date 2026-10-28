#!/usr/bin/env bash
# Compile the game on a supported desktop host.
set -euo pipefail
cd "$(dirname "$0")/.."
args=(-l SDL2 "--heap-size=${HEAP_SIZE:-134217728}")
lib_dir="${SDL_LIB_DIR:-}"
if [[ -z "$lib_dir" && "$(uname -s)" == Darwin ]]; then
  lib_dir="$(brew --prefix sdl2)/lib"
fi
if [[ -n "$lib_dir" ]]; then args+=(-L "$lib_dir"); fi
"${ELEPHC:-elephc}" "${args[@]}" main.php

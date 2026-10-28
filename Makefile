.DEFAULT_GOAL := build
.PHONY: build run test check package release release-dry-run clean

ELEPHC ?= elephc
HEAP_SIZE ?= 134217728
VERSION ?= dev
DIST_DIR ?= dist
export ELEPHC HEAP_SIZE VERSION DIST_DIR SDL_LIB_DIR

build:
	@./scripts/build.sh

run: build
	@./main

test:
	@"$$ELEPHC" test tests --heap-size="$$HEAP_SIZE"

check:
	@for script in scripts/*.sh; do bash -n "$$script"; done
	@python3 -m unittest discover -s scripts/tests -p 'test_*.py' -v
	@if command -v shellcheck >/dev/null 2>&1 && shellcheck --version >/dev/null 2>&1; then shellcheck scripts/*.sh; fi

package: build
	@python3 scripts/package.py

# Like ggw: propose a version, confirm, create an annotated tag and push it.
release:
	@./scripts/release.sh

release-dry-run:
	@./scripts/release.sh --dry-run

clean:
	@rm -f main main.s main.o

# HackTrader dev Makefile
# Run with `make <target>` from the repo root on your Mac.
#
# IMPORTANT: when copy-pasting these commands, don't paste trailing
# `# comments` on the same line — some zsh setups choke on `#`.

.PHONY: help install lint lint-php lint-py format-py test test-py test-php deploy sync hooks check-php

# --- Pinned tool versions (bump when ready) ---
PHPSTAN_VERSION := 1.11.10
PHPSTAN_PHAR    := bin/phpstan.phar
PHPSTAN_URL     := https://github.com/phpstan/phpstan/releases/download/$(PHPSTAN_VERSION)/phpstan.phar

RUFF_VERSION := 0.6.9
RUFF_BIN     := bin/ruff
UNAME_M      := $(shell uname -m)
ifeq ($(UNAME_M),arm64)
    RUFF_ARCH := aarch64
else
    RUFF_ARCH := x86_64
endif
RUFF_URL := https://github.com/astral-sh/ruff/releases/download/$(RUFF_VERSION)/ruff-$(RUFF_ARCH)-apple-darwin.tar.gz

# Python venv used for pytest. Avoids PEP 668 fights with the system Python.
VENV    := .venv
PYTEST  := $(VENV)/bin/pytest

# PHPUnit phar (single-file, runs with `php`).
PHPUNIT_VERSION := 10.5.20
PHPUNIT_PHAR    := bin/phpunit.phar
PHPUNIT_URL     := https://phar.phpunit.de/phpunit-$(PHPUNIT_VERSION).phar

help:
	@echo "Targets:"
	@echo "  install     Download phpstan.phar, phpunit.phar, ruff; create .venv with pytest"
	@echo "  hooks       Install git pre-commit hook into .git/hooks/"
	@echo "  lint        Run all linters (PHP + Python)"
	@echo "  lint-php    phpstan over the PHP files (needs 'php' on PATH)"
	@echo "  lint-py     ruff over the Python files"
	@echo "  format-py   ruff format the Python files in place"
	@echo "  test        Run all tests (Python + PHP)"
	@echo "  test-py     pytest under .venv"
	@echo "  test-php    PHPUnit (needs 'php' on PATH)"
	@echo "  sync        Pull /var/www/html down from dev.hacktrader.com"
	@echo "  deploy      Commit + push to GitHub + rsync up to dev"
	@echo "                usage:  make deploy MSG=\"what changed\""

install: $(PHPSTAN_PHAR) $(RUFF_BIN) $(PHPUNIT_PHAR) $(PYTEST)
	@echo
	@echo "phpstan: $$(php $(PHPSTAN_PHAR) --version 2>/dev/null || echo '(php not installed — run: brew install php)')"
	@echo "phpunit: $$(php $(PHPUNIT_PHAR) --version 2>/dev/null || echo '(php not installed — run: brew install php)')"
	@echo "ruff:    $$($(RUFF_BIN) --version)"
	@echo "pytest:  $$($(PYTEST) --version 2>/dev/null | head -1)"

# Download phpstan phar on first use; cached afterwards.
$(PHPSTAN_PHAR):
	@mkdir -p bin
	@echo "Downloading phpstan $(PHPSTAN_VERSION)..."
	@curl -L --fail --silent --show-error -o $@ $(PHPSTAN_URL)
	@chmod +x $@
	@echo "Saved: $@"

# Download ruff binary on first use; cached afterwards.
$(RUFF_BIN):
	@mkdir -p bin
	@echo "Downloading ruff $(RUFF_VERSION) ($(RUFF_ARCH))..."
	@TMPDIR=$$(mktemp -d) && \
		curl -L --fail --silent --show-error -o "$$TMPDIR/ruff.tar.gz" "$(RUFF_URL)" && \
		tar -xzf "$$TMPDIR/ruff.tar.gz" -C "$$TMPDIR" && \
		find "$$TMPDIR" -name ruff -type f -perm -u=x -exec cp {} $@ \; && \
		chmod +x $@ && \
		rm -rf "$$TMPDIR"
	@echo "Saved: $@"

# Download phpunit phar on first use; cached afterwards.
$(PHPUNIT_PHAR):
	@mkdir -p bin
	@echo "Downloading phpunit $(PHPUNIT_VERSION)..."
	@curl -L --fail --silent --show-error -o $@ $(PHPUNIT_URL)
	@chmod +x $@
	@echo "Saved: $@"

# Create venv and install pytest into it. Sidesteps PEP 668 system Python.
$(PYTEST):
	@echo "Creating $(VENV)/ and installing pytest..."
	@python3 -m venv $(VENV)
	@$(VENV)/bin/pip install --quiet --upgrade pip
	@$(VENV)/bin/pip install --quiet 'pytest>=8'
	@echo "Saved: $(PYTEST)"

hooks:
	@bash hooks/install.sh

check-php:
	@command -v php >/dev/null || { \
		echo "ERROR: 'php' not on PATH."; \
		echo "Install with:  brew install php"; \
		echo "Skip PHP linting for now with:  make lint-py"; \
		exit 1; \
	}

lint: lint-php lint-py

lint-php: check-php $(PHPSTAN_PHAR)
	@php $(PHPSTAN_PHAR) analyse --memory-limit=512M

lint-py: $(RUFF_BIN)
	@$(RUFF_BIN) check .

format-py: $(RUFF_BIN)
	@$(RUFF_BIN) format .

test: test-py test-php

test-py: $(PYTEST)
	@$(PYTEST) tests/python/ -v

test-php: check-php $(PHPUNIT_PHAR)
	@php $(PHPUNIT_PHAR) --colors=auto --testdox tests/php/

sync:
	@bash sync_hacktrader.sh

deploy:
ifndef MSG
	$(error MSG is required: make deploy MSG="what changed")
endif
	@bash deploy.sh "$(MSG)"

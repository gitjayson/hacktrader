"""
pytest fixtures for HackTrader Python tests.

run-brk.py and generate-correlations.py have hyphens in their names, which
blocks regular Python `import`. We load them once here via importlib and
expose them as session-scoped fixtures.
"""

from __future__ import annotations

import importlib.util
import sys
from pathlib import Path

import pytest

REPO_ROOT = Path(__file__).resolve().parents[2]


def _load_module(module_name: str, source_path: Path):
    """Load a Python file as a module under a given name."""
    spec = importlib.util.spec_from_file_location(module_name, source_path)
    if spec is None or spec.loader is None:
        raise ImportError(f"Could not load {source_path}")
    module = importlib.util.module_from_spec(spec)
    sys.modules[module_name] = module
    spec.loader.exec_module(module)
    return module


@pytest.fixture(scope="session")
def run_brk():
    """The run-brk.py module, loaded under the name 'run_brk'."""
    return _load_module("run_brk", REPO_ROOT / "run-brk.py")


@pytest.fixture(scope="session")
def generate_correlations():
    """The generate-correlations.py module, loaded under the name 'generate_correlations'."""
    return _load_module("generate_correlations", REPO_ROOT / "generate-correlations.py")

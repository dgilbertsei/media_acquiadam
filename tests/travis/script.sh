#!/usr/bin/env bash

# NAME
#     script.sh - Avoid deprecated code analysis on test files.
#
# SYNOPSIS
#     script.sh
#
# DESCRIPTION
#     Delete the tests/src directory during STRICT_DEPRECATED_CODE_SCAN job to avoid deprecated code errors.

if [[ "$ORCA_JOB" == "STRICT_DEPRECATED_CODE_SCAN" ]]; then

  cd "$(dirname "$0")" || exit 1;
  cd ../../ || exit 1;

  echo "Delete the tests/src directory to avoid deprecated code errors due to PHPUnit version mismatch."
  rm -Rf tests/src || exit 1;

fi

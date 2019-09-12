#!/usr/bin/env bash

# NAME
#     install.sh - Install Travis CI dependencies
#
# SYNOPSIS
#     install.sh
#
# DESCRIPTION
#     Creates the test fixture.

cd "$(dirname "$0")"

# Reuse ORCA's own includes.
source ../../../orca/bin/travis/_includes.sh

# Target the deprecated code scan job.
[[ "$ORCA_JOB" = "DEPRECATED_CODE_SCAN_SUT" ]] || exit 0

composer --working-dir="$ORCA_FIXTURE_DIR" require drupal/entity_browser

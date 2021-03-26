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

# Exit early in the absence of a fixture.
[[ -d "$ORCA_FIXTURE_DIR" ]] || exit 0

# Target the deprecated code scan job.
if [[ "$ORCA_JOB" = "LOOSE_DEPRECATED_CODE_SCAN" || "$ORCA_JOB" = "STRICT_DEPRECATED_CODE_SCAN" ]]; then
  (
    composer --working-dir="$ORCA_FIXTURE_DIR" require drupal/entity_browser
    composer --working-dir="$ORCA_FIXTURE_DIR" require drupal/linkit:~5.0
  )
fi
exit 0

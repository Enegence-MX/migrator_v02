#!/bin/bash
let MAXERRORS=0
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'
excluded_dirs=""

# Detect running environment
if [ -f /.dockerenv ]; then
    # Docker container
    PHP_BIN="php"
elif command -v docker &> /dev/null && docker ps --format '{{.Names}}' 2>/dev/null | grep -q 'migrator_v02'; then
    # Host machine
    PHP_BIN="docker compose exec -T migrator php"
else
    # CI environment
    PHP_BIN="php"
fi

processOutput(){
    output=$1
    if [ -n "$output" ]; then
        # Count only actual ERROR lines (format: " | ERROR | "), not WARNING lines
        errors=$(echo "$output" | grep -E " \| ERROR \| " || true)

        # Count errors
        if [ -z "$errors" ]; then
            error_count=0
        else
            error_count=$(echo "$errors" | wc -l | tr -d ' ')
        fi

        echo -e "${NC}Errors found ${error_count}."
        if [ "$error_count" -gt "$MAXERRORS" ]; then
            echo -e "${RED}Code standar errors exceds max allowed."
            echo -e "${NC}"
            echo "$output"
            return 1;
        fi
        echo -e "${GREEN}Code standar review success."
        echo -e "${NC}"
        return 0;
    else
        echo -e "${GREEN}Code standar review success."
        echo -e "${NC}"
        return 0;
    fi
}

if [ "$#" -eq 0 ]; then
    # phpcs command with optional --ignore parameter
    phpcs_cmd="$PHP_BIN vendor/bin/phpcs --standard=PSR2"
    if [ -n "$excluded_dirs" ]; then
        phpcs_cmd="$phpcs_cmd --ignore=\"$excluded_dirs\""
    fi
    phpcs_cmd="$phpcs_cmd app"

    set +e  # Disable exit on error for entire block
    output=$(eval "$phpcs_cmd" 2>&1)
    phpcs_exit=$?

    # If phpcs had a processing error
    if [ $phpcs_exit -gt 3 ]; then
        echo -e "${RED}Error: phpcs command failed with exit code $phpcs_exit${NC}"
        echo "$output"
        # Check if script is being sourced or executed
        if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
            exit $phpcs_exit
        else
            return $phpcs_exit
        fi
    fi

    processOutput "$output"
    script_exit=$?

    # Check if script is being sourced or executed
    if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
        # Script is being executed directly
        exit $script_exit
    else
        # Script is being sourced
        return $script_exit
    fi
else
    for filename in "$@"; do
        found_files=$(find app -type f -name "$filename")
        if [ -z "$found_files" ]; then
            echo "No files found matching '$filename'."
        else
            for file in $found_files; do
                eval "$PHP_BIN vendor/bin/phpcs --standard=PSR2 \"$file\""
                output=$(eval "$PHP_BIN vendor/bin/phpcs --standard=PSR2 \"$file\"")
                processOutput "$output"
            done
        fi
    done
    # echo ""
fi


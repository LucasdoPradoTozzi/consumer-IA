#!/bin/bash

# Script to pull Ollama model after containers are up
# Usage: ./pull-ollama-model.sh [profile|model]
# Profiles: low, medium, high

CONTAINER_NAME=${APP_NAME:-laravelapp}-ollama

# Check if argument is a profile
case "$1" in
    low)
        MODEL="qwen2.5:7b-q4"
        echo "Using 'low' profile"
        ;;
    medium)
        MODEL="qwen2.5:14b"
        echo "Using 'medium' profile"
        ;;
    high)
        MODEL="qwen2.5vl:32b"
        echo "Using 'high' profile"
        ;;
    "")
        MODEL="qwen2.5:7b-q4"
        echo "No profile specified, using 'low' profile"
        ;;
    *)
        MODEL="$1"
        echo "Using custom model"
        ;;
esac

echo "Pulling Ollama model: $MODEL"
echo "Container: $CONTAINER_NAME"
echo ""

docker exec -it $CONTAINER_NAME ollama pull $MODEL

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Model $MODEL pulled successfully!"
    echo ""
    echo "To test the model:"
    echo "docker exec -it $CONTAINER_NAME ollama run $MODEL"
else
    echo ""
    echo "✗ Failed to pull model $MODEL"
    exit 1
fi

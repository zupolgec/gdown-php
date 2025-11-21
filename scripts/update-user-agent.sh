#!/bin/bash
set -e

# Fetch the latest Chrome User-Agent for macOS
USER_AGENT=$(curl -s 'https://jnrbsn.github.io/user-agents/user-agents.json' | \
  jq -r '.[] | select(. | contains("Chrome")) | select(. | contains("Macintosh"))' | head -1)

if [ -z "$USER_AGENT" ]; then
  echo "❌ Failed to fetch User-Agent"
  exit 1
fi

echo "✓ Latest User-Agent: $USER_AGENT"

# Update UserAgent.php constant
USER_AGENT_FILE="src/UserAgent.php"
if [ -f "$USER_AGENT_FILE" ]; then
  # Extract current User-Agent
  CURRENT_UA=$(grep "public const DEFAULT = " "$USER_AGENT_FILE" | sed "s/.*= '\(.*\)';/\1/")
  
  if [ "$CURRENT_UA" = "$USER_AGENT" ]; then
    echo "✓ User-Agent is already up to date in $USER_AGENT_FILE"
  else
    echo "↻ Updating User-Agent in $USER_AGENT_FILE"
    
    # Update the date comment
    TODAY=$(date +%Y-%m-%d)
    sed -i '' "s/Updated: [0-9-]*/Updated: $TODAY/" "$USER_AGENT_FILE"
    
    # Extract Chrome version from new UA
    CHROME_VERSION=$(echo "$USER_AGENT" | sed -n 's/.*Chrome\/\([0-9.]*\).*/\1/p')
    sed -i '' "s/Chrome Version: [0-9.]*/Chrome Version: $CHROME_VERSION/" "$USER_AGENT_FILE"
    
    # Update the actual UA string
    sed -i '' "s|public const DEFAULT = '.*';|public const DEFAULT = '$USER_AGENT';|" "$USER_AGENT_FILE"
    
    echo "✓ Updated $USER_AGENT_FILE"
    echo "  - Date: $TODAY"
    echo "  - Chrome: $CHROME_VERSION"
  fi
else
  echo "❌ $USER_AGENT_FILE not found!"
  exit 1
fi

echo ""
echo "✅ User-Agent update complete!"
echo ""
echo "The User-Agent is now centralized in src/UserAgent.php"
echo "Both Downloader and FolderDownloader use UserAgent::DEFAULT"

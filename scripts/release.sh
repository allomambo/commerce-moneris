#!/bin/bash

set -e

# Ensure script is run from project root (where composer.json exists)
if [[ ! -f "composer.json" ]]; then
  echo "❌ composer.json not found! Please run this script from the project root directory."
  exit 1
fi

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 [patch|minor|major]"
  exit 1
fi

BUMP=$1

# Check for gh CLI
if ! command -v gh &> /dev/null; then
  echo "❌ GitHub CLI (gh) is not installed."
  echo "Install it with:"
  echo "  brew install gh      # macOS"
  echo "  sudo apt install gh  # Ubuntu/Debian"
  echo "  choco install gh     # Windows"
  exit 1
fi

# Check for gh authentication
if ! gh auth status &> /dev/null; then
  echo "❌ GitHub CLI is not authenticated."
  echo "Authenticate with:"
  echo "  gh auth login"
  exit 1
fi

echo "🔍 Checking working directory..."
if [[ -n $(git status --porcelain) ]]; then
  echo "❌ Please commit or stash your changes before releasing."
  exit 1
fi

echo "🚀 Checking out dev and pulling latest..."
git checkout dev
git pull

echo "🔢 Reading current version from composer.json..."
VERSION=$(jq -r '.version' composer.json)
IFS='.' read -r MAJOR MINOR PATCH <<< "$VERSION"
echo "Current version: $VERSION"

case $BUMP in
  patch)
    PATCH=$((PATCH + 1))
    ;;
  minor)
    MINOR=$((MINOR + 1))
    PATCH=0
    ;;
  major)
    MAJOR=$((MAJOR + 1))
    MINOR=0
    PATCH=0
    ;;
  *)
    echo "❌ Invalid bump type: $BUMP"
    exit 1
    ;;
esac

NEW_VERSION="$MAJOR.$MINOR.$PATCH"
RELEASE_BRANCH="release/v$NEW_VERSION"
echo "🔖 New version: $NEW_VERSION"

echo "🌱 Creating release branch: $RELEASE_BRANCH"
git checkout -b "$RELEASE_BRANCH"

echo "✏️  Updating composer.json version..."
jq ".version = \"$NEW_VERSION\"" composer.json > composer.json.tmp && mv composer.json.tmp composer.json

echo "📦 Committing and tagging release..."
git add composer.json
git commit -m "Release v$NEW_VERSION"
git tag "v$NEW_VERSION"

echo "⬆️  Merging release branch into main..."
git checkout main
git pull
git merge --no-ff "$RELEASE_BRANCH" -m "Merge release v$NEW_VERSION"
git push origin main

echo "⬆️  Merging release branch back into dev..."
git checkout dev
git merge --no-ff "$RELEASE_BRANCH" -m "Merge release v$NEW_VERSION into dev"
git push origin dev

echo "🏷️  Pushing tags..."
git push origin "v$NEW_VERSION"

# Generate release notes from commits since last tag
echo "📝 Generating release notes..."
PREV_TAG=$(git tag --sort=-creatordate | sed -n 2p)
NEW_TAG="v$NEW_VERSION"
if [[ -z "$PREV_TAG" ]]; then
  # No previous tag, get all commits
  RELEASE_NOTES=$(git log --pretty=format:"- %s")
else
  RELEASE_NOTES=$(git log --pretty=format:"- %s" "$PREV_TAG..$NEW_TAG")
fi

# Create GitHub release with generated notes
echo "🚀 Creating GitHub release..."
gh release create "v$NEW_VERSION" \
  --title "v$NEW_VERSION" \
  --notes "$RELEASE_NOTES"

echo "🧹 Deleting release branch..."
git checkout dev
git branch -d "$RELEASE_BRANCH"
if git ls-remote --exit-code --heads origin "$RELEASE_BRANCH" &>/dev/null; then
  git push origin --delete "$RELEASE_BRANCH"
else
  echo "ℹ️  Remote branch $RELEASE_BRANCH does not exist, skipping remote delete."
fi

echo "✅ Released version $NEW_VERSION"

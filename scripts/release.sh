#!/bin/bash

set -e

# Ensure script is run from project root (where composer.json exists)
if [[ ! -f "composer.json" ]]; then
  echo "❌ composer.json not found! Please run this script from the project root directory."
  exit 1
fi

if [[ $# -ne 1 ]]; then
  echo "Usage: $0 [patch|minor|major|alpha|beta]"
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
echo "Current version: $VERSION"

# Parse version components (handles X.Y.Z or X.Y.Z-prerelease.N)
if [[ $VERSION =~ ^([0-9]+)\.([0-9]+)\.([0-9]+)(-([a-z]+)\.([0-9]+))?$ ]]; then
  MAJOR="${BASH_REMATCH[1]}"
  MINOR="${BASH_REMATCH[2]}"
  PATCH="${BASH_REMATCH[3]}"
  PRERELEASE_TYPE="${BASH_REMATCH[5]}"  # alpha or beta
  PRERELEASE_NUM="${BASH_REMATCH[6]}"   # the number after alpha/beta
else
  echo "❌ Invalid version format: $VERSION"
  exit 1
fi

case $BUMP in
  patch)
    if [[ -n "$PRERELEASE_TYPE" ]]; then
      echo "❌ Cannot do a patch release from a prerelease version. Release a stable version first or continue with alpha/beta."
      exit 1
    fi
    PATCH=$((PATCH + 1))
    NEW_VERSION="$MAJOR.$MINOR.$PATCH"
    ;;
  minor)
    if [[ -n "$PRERELEASE_TYPE" ]]; then
      echo "❌ Cannot do a minor release from a prerelease version. Release a stable version first or continue with alpha/beta."
      exit 1
    fi
    MINOR=$((MINOR + 1))
    PATCH=0
    NEW_VERSION="$MAJOR.$MINOR.$PATCH"
    ;;
  major)
    if [[ -n "$PRERELEASE_TYPE" ]]; then
      echo "❌ Cannot do a major release from a prerelease version. Release a stable version first or continue with alpha/beta."
      exit 1
    fi
    MAJOR=$((MAJOR + 1))
    MINOR=0
    PATCH=0
    NEW_VERSION="$MAJOR.$MINOR.$PATCH"
    ;;
  alpha)
    if [[ "$PRERELEASE_TYPE" == "alpha" ]]; then
      # Already alpha, increment the alpha number
      PRERELEASE_NUM=$((PRERELEASE_NUM + 1))
      NEW_VERSION="$MAJOR.$MINOR.$PATCH-alpha.$PRERELEASE_NUM"
    elif [[ "$PRERELEASE_TYPE" == "beta" ]]; then
      echo "❌ Cannot go from beta back to alpha. Please release a stable version first."
      exit 1
    else
      # Stable version, bump patch and start alpha.1
      PATCH=$((PATCH + 1))
      NEW_VERSION="$MAJOR.$MINOR.$PATCH-alpha.1"
    fi
    ;;
  beta)
    if [[ "$PRERELEASE_TYPE" == "alpha" ]]; then
      # Moving from alpha to beta.1 (same base version)
      NEW_VERSION="$MAJOR.$MINOR.$PATCH-beta.1"
    elif [[ "$PRERELEASE_TYPE" == "beta" ]]; then
      # Already beta, increment the beta number
      PRERELEASE_NUM=$((PRERELEASE_NUM + 1))
      NEW_VERSION="$MAJOR.$MINOR.$PATCH-beta.$PRERELEASE_NUM"
    else
      # Stable version, bump patch and start beta.1
      PATCH=$((PATCH + 1))
      NEW_VERSION="$MAJOR.$MINOR.$PATCH-beta.1"
    fi
    ;;
  *)
    echo "❌ Invalid bump type: $BUMP"
    exit 1
    ;;
esac

echo ""
echo "🔖 New version will be: $NEW_VERSION"
echo "📋 Current version: $VERSION"
echo ""
read -p "❓ Do you want to proceed with this release? (y/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "❌ Release cancelled."
  exit 0
fi

echo "✅ Proceeding with release v$NEW_VERSION..."
echo ""

# Check if this is a prerelease (alpha/beta)
if [[ "$NEW_VERSION" =~ -(alpha|beta)\. ]]; then
  echo "📦 Creating prerelease v$NEW_VERSION directly on dev branch..."
  
  echo "✏️  Updating composer.json version..."
  jq ".version = \"$NEW_VERSION\"" composer.json > composer.json.tmp && mv composer.json.tmp composer.json
  
  echo "📦 Committing and tagging prerelease..."
  git add composer.json
  git commit -m "Release v$NEW_VERSION"
  git tag "v$NEW_VERSION"
  
  echo "⬆️  Pushing to dev..."
  git push origin dev
  
  echo "🏷️  Pushing tags..."
  git push origin "v$NEW_VERSION"
else
  # Stable release - use release branch and merge to main
  RELEASE_BRANCH="release/v$NEW_VERSION"
  
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
fi

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
if [[ "$NEW_VERSION" =~ -(alpha|beta)\. ]]; then
  # Mark as prerelease for alpha/beta versions
  gh release create "v$NEW_VERSION" \
    --title "v$NEW_VERSION" \
    --notes "$RELEASE_NOTES" \
    --prerelease
else
  gh release create "v$NEW_VERSION" \
    --title "v$NEW_VERSION" \
    --notes "$RELEASE_NOTES"
fi

# Only cleanup release branch if it was created (stable releases only)
if [[ ! "$NEW_VERSION" =~ -(alpha|beta)\. ]]; then
  echo "🧹 Deleting release branch..."
  git checkout dev
  git branch -d "$RELEASE_BRANCH"
  if git ls-remote --exit-code --heads origin "$RELEASE_BRANCH" &>/dev/null; then
    git push origin --delete "$RELEASE_BRANCH"
  else
    echo "ℹ️  Remote branch $RELEASE_BRANCH does not exist, skipping remote delete."
  fi
fi

echo "✅ Released version $NEW_VERSION"

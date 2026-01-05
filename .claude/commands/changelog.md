Generate or update the CHANGELOG.md [Unreleased] section based on changes in this branch.

## Instructions

1. First, identify what has changed by running:
   - `git log develop..HEAD --oneline` to see commits on this branch
   - `git diff develop --stat` to see files changed

2. Read the current CHANGELOG.md to see any existing entries in [Unreleased]

3. Generate user-friendly changelog entries for the changes:
   - **Added**: New features or capabilities
   - **Changed**: Changes to existing functionality
   - **Fixed**: Bug fixes
   - **Removed**: Removed features or capabilities

4. Writing guidelines:
   - Write in past tense ("Added property map view" not "Add property map view")
   - Focus on user impact, not technical details
   - Use simple language end-users can understand
   - Skip internal changes (CI/CD, tests, dependency updates, refactoring without user impact)
   - Each entry should be one clear, concise line

5. Update the [Unreleased] section in CHANGELOG.md:
   - Preserve any existing entries
   - Add new entries under appropriate categories
   - Remove empty categories

6. Show me the updated [Unreleased] section for review before I commit

## Example Output

```markdown
## [Unreleased]

### Added
- Property map view with interactive Google Maps integration
- Geocoding support for property addresses
- AppFolio link on property detail page

### Fixed
- Race condition when adding property flags simultaneously
```

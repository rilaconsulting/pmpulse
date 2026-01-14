Address all code review comments on the current branch's PR.

## Instructions

1. First, identify the PR for this branch:
   ```bash
   gh pr view --json number,url,reviews,comments
   ```

2. Get ALL review comments on the PR with pagination:
   ```bash
   gh api repos/{owner}/{repo}/pulls/{pr_number}/comments --paginate
   ```

   Also check for PR-level comments with pagination:
   ```bash
   gh api repos/{owner}/{repo}/issues/{pr_number}/comments --paginate
   ```

   **IMPORTANT**: Always use `--paginate` to ensure you fetch ALL comments, not just the first page.

3. For each comment/suggestion:
   - Read the referenced file and understand the context
   - Determine if it's actionable now or should be tech debt
   - **Actionable**: Make the code change to address it
   - **Tech debt**: Note it for later Linear issue creation

4. After addressing comments in code:
   - Commit the changes with a clear message referencing the PR
   - Push the changes to the branch

5. **REQUIRED: Reply inline to EVERY code suggestion**

   You MUST respond to every single review comment. No exceptions.

   For review comments (code suggestions), reply inline:
   ```bash
   gh api repos/{owner}/{repo}/pulls/{pr_number}/comments/{comment_id}/replies -f body="message"
   ```

   For PR-level issue comments:
   ```bash
   gh api repos/{owner}/{repo}/issues/{pr_number}/comments -f body="message"
   ```

   Use these response formats:
   - **Fixed**: "✅ Fixed in [commit_sha] - [brief description of change]"
   - **Already done**: "✅ Already addressed - [explanation]"
   - **Tech debt**: "📋 Created Linear issue [PMP-XXX] to track this improvement"
   - **Won't fix**: "ℹ️ [Explanation of why this won't be changed]"
   - **Question**: "❓ [Follow-up question for clarification]"

6. For tech debt items, create Linear issues:
   - Use the "tech debt" label
   - Reference the PR comment in the description
   - Include enough context to action later

7. Verify all comments have been responded to:
   - Re-fetch comments with `--paginate`
   - Confirm each comment has a reply from you
   - If any are missing responses, add them

8. Provide a summary of:
   - Total comments found
   - Comments addressed with code changes
   - Tech debt issues created
   - Any comments that need discussion

## Example Summary

```
## PR Review Summary

**Total comments reviewed: 8**

### ✅ Addressed (5 comments)
- Fixed unused import in PropertyController.php
- Added error handling for geocoding failure
- Renamed misleading variable in Settings.jsx
- Added null check as suggested
- Updated docstring per feedback

### 📋 Tech Debt Created (2 issues)
- PMP-127: Add comprehensive geocoding tests
- PMP-128: Refactor flag management to separate service

### 💬 Needs Discussion (1 comment)
- @reviewer asked about caching strategy - awaiting clarification

### ✅ All 8 comments have been responded to inline
```

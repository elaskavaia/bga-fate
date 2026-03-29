---
name: review-diff
description: Review uncommitted changes for bugs and issues, one at a time
disable-model-invocation: true
---

Review all uncommitted changes in the working tree to find bugs.

Steps:
1. Run `git diff` to get all uncommitted changes, and `git status -s` for untracked files
3. Read any new (untracked) files in full
4. Analyze the changes thoroughly, focusing on:
   - Code correctness and logic bugs
   - Consistency with existing patterns in the codebase
   - Off-by-one errors, wrong comparisons, stale comments
   - Missing cache invalidation or state cleanup
   - Return value semantics mismatches between callers and callees
   - Test correctness (do assertions match the new semantics?)
   - Missing unit tests
   - If unsure whether something is a bug, investigate further (read related code, check callers) before reporting
5. Report issues **one at a time**, prompting the user before moving to the next
6. For each issue, show the file and line, the problematic code, and explain why it's a bug
7. If you're unsure whether something is a bug, ask the user to confirm rather than reporting it as definite
8. Do not report style nits or minor improvements — focus on actual bugs and correctness issues
9. Do not provide a summary of changes unless asked

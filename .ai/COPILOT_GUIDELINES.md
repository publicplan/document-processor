# Copilot AI Agent Guidelines

## Commit Policy

**Copilot and all AI agents MUST NOT commit code or changes to the repository.**

### Workflow
1. AI agents prepare changes (git add, git status, etc.)
2. AI agents present changes to the user for review
3. **Only the user executes git commit**
4. AI agents do not execute `git commit` under any circumstances

### Rationale
- Ensures human oversight and accountability for all repository changes
- Maintains clear audit trail of who authored changes
- Prevents accidental or unauthorized commits
- Allows user to review before committing

---

## Code Changes

- AI agents provide complete, working solutions
- Changes are surgical and fully address the request
- Related bugs tightly coupled to the task are fixed
- Validation runs before presenting to user
- User always reviews before committing

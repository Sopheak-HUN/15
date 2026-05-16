# Skill: Version Control and Project Updates

## Context
Use this skill when managing project versions, updating dependencies, or modifying core project configuration. Maintaining version consistency across files ensures that the API reflects the correct deployment state.

## Guidelines

### 1. Versioning Pattern
We follow a modified Semantic Versioning pattern: `{MAJOR}.{MINOR or features}.{PATCH}`.
- **MAJOR**: Incompatible API changes or significant architectural shifts.
- **MINOR or features**: New features or major module additions.
- **PATCH**: Backwards-compatible bug fixes and small improvements.

### 2. Version Updates
When updating the project version, you **MUST** update all of the following files simultaneously:
1. **`package.json`**: Update the `"version"` field (source of truth).
2. **`README.md`**: Update the version indicator at the top.
3. **`SECURITY.md`**: Update the "Supported Versions" table to include the new version range if applicable.

### 3. Implementation Process
When a version update is requested:
1. Verify the current version in `package.json`.
2. Propose the new version based on the changes made.
3. Update `package.json`.
4. Update `README.md`.
5. Ensure the change is reflected in the API's base endpoint (if applicable, via `package.json` import).

## Best Practices
- **Consistency**: Never update the version in only one file.
- **Documentation**: Briefly mention the reason for the version bump in the commit message or PR description.
- **Validation**: Ensure that the version follows the `x.y.z` format.

## Troubleshooting
- **Version Mismatch**: If the API reports a different version than the README, sync them immediately using `package.json` as the reference.
- **Invalid Format**: Ensure no letters or extra characters are present in the version string (e.g., use `0.14.1`, not `v0.14.1`).
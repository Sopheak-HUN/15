# Skill: Code Documentation Standards

## Context
Use this skill when documenting API endpoints, controllers, services, and complex business logic. Consistent documentation ensures that AI agents and human contributors can quickly understand the purpose and requirements of each function.

## Guidelines

### 1. JSDoc Pattern
All public methods, controllers, and service functions should follow this standardized JSDoc format:

```typescript
/**
 * @description Brief description of the function's purpose
 * @method HTTP_METHOD (if applicable)
 * @param { Type } name Description
 * @param { Type } [optionalName] Description for optional parameter
 * @returns { Type } Description of return value
 */
```

### 2. Implementation Example
For a typical API endpoint handler:

```typescript
/**
 * @description Submit Contact Form
 * @method POST
 * @param { String } name Sender Name
 * @param { String } email Sender Email
 * @param { String } [subject] Message Subject
 * @param { String } message Message Content
 */
export async function submitContactForm(request: Request, env: Env) {
    // implementation
}
```

### 3. When to Document
- **Controllers**: Mandatory for all endpoint handlers.
- **Repositories**: Required for complex queries or data transformations.
- **Services**: Required for business logic orchestration.
- **Interfaces**: Use inline comments for non-obvious properties.

## Best Practices
- **Clarity**: Keep descriptions concise but informative.
- **Types**: Always specify the data type in the `@param` tag.
- **Optionality**: Use square brackets `[parameter]` for optional inputs.
- **Language**: Always use English for comments and documentation.

## Troubleshooting
- **Missing @method**: Ensure HTTP methods are specified for routes.
- **Inconsistent Types**: Verify that the type in JSDoc matches the TypeScript interface.
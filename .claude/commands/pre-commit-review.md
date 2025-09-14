# Instructions: `pre-commit-review`

Please perform a comprehensive code review for the uncommited changes. Follow these steps:

### 1. Code Quality Review
Analyze all changes and evaluate:

**Architecture & Design:**
- Does the code follow Symfony >6.4 best practices and conventions?
- Does the code follow single responsibility principle with clear separation of concerns?
- Are new dependencies justified and properly integrated?

**Security & Performance:**
- Are there any security vulnerabilities or exposed sensitive data?
- Is caching implemented where beneficial?
- Does memory usage is optimal?

**Code Standards:**
- Does the code follow PSR standards and project conventions?
- Are variable names and methods descriptive and consistent?
- Is error handling comprehensive and appropriate?
- Are comments and documentation adequate?
- Does all methods have a PHPDocs block?
- Does test method's names are snake_case_named?

**Testing & Reliability:**
- Are unit tests present for the new functionality?
- Do integration tests cover the main workflows?
- Are edge cases and error scenarios tested?
- Is the test coverage adequate?

### 2. Risk Assessment
Identify potential risks:
- Breaking changes that might affect existing functionality
- Database migration requirements
- Performance impact on large datasets
- Compatibility issues with external integrations

### 3. Recommendations
Provide specific, actionable recommendations:
- Code improvements and refactoring suggestions
- Missing tests or documentation
- Performance optimizations
- Security enhancements

### 4. Approval Decision
Based on the analysis, provide one of:
-  **APPROVED** - Ready to merge
- � **APPROVED WITH MINOR CHANGES** - Can merge after addressing minor issues
- L **NEEDS WORK** - Requires significant changes before merge

Include a summary of the most critical issues that must be addressed before merging.

---

*This review should focus on maintainability, security, performance, and adherence to the Symfony best practices.*

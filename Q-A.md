# Viva Questions and Answers

## 1. Project basics

### Q1. What is this project?

It is a web portal for managing academic projects from proposal creation to staged submission, faculty evaluation, appeals, feedback, and final sign-off.

### Q2. Who uses the system?

There are three roles: administrator, faculty, and student. Each role has a separate dashboard and permission set.

### Q3. What problem does it solve?

It replaces scattered project documents and manual evaluation with one controlled workflow containing deadlines, files, marks, feedback, notifications, and history.

### Q4. What technology is used?

The application uses dependency-free PHP 8.1+, PDO, MySQL/MariaDB, HTML, and CSS. No Composer or frontend build system is required.

### Q5. What is the main workflow?

An administrator creates accounts and configuration, a student submits a project proposal, an administrator approves it and assigns a guide, the student submits stages, faculty evaluates them, and an authorized user completes final sign-off.

## 2. Roles and authorization

### Q6. What can an administrator do?

An administrator manages users, departments, batches, stages, deadlines, rubrics, project approval, guides, appeals, and final sign-off.

### Q7. What can a faculty member do?

Faculty can view assigned projects, download authorized submissions, provide feedback, evaluate using rubrics, and make evaluation decisions.

### Q8. What can a student do?

Students can create proposals, manage their team, submit stage files, view feedback and results, reply to feedback, and appeal an eligible rejected submission.

### Q9. How is authorization enforced?

Every action checks the logged-in user’s role and database relationships such as project membership or guide assignment. Foreign keys alone are not enough for these business rules.

### Q10. Can a student access another team’s project?

No. Project queries require a matching project-membership record for that student.

### Q11. Can faculty evaluate any project?

No. Faculty evaluation requires an active primary-guide assignment for that project.

## 3. Authentication and sessions

### Q12. How are passwords stored?

Passwords are stored as bcrypt hashes. The original password is never stored in the database.

### Q13. Why use hashing instead of encryption?

Passwords only need verification, not recovery. Hashing is one-way, so a database leak does not directly reveal the original password.

### Q14. What is a temporary password?

It is an initial password assigned by an administrator. The `force_password_change` flag requires the user to replace it after the first login.

### Q15. How does logout work?

The session is cleared, the session cookie is removed, and the session is destroyed.

### Q16. How are sessions protected?

Sessions use strict mode, HTTP-only cookies, SameSite protection, idle timeout, absolute timeout, and per-user session-version revocation.

### Q17. What happens after a password change?

The user’s session version is incremented, reset tokens are invalidated, and the current session is regenerated.

### Q18. How does login rate limiting work?

Failed attempts are stored by hashed email and IP address. Recent failures are counted and excessive attempts are temporarily blocked.

### Q19. How does password recovery work?

The system generates a random token, stores only its SHA-256 hash, emails the raw token link, expires it after a short period, and marks it used after a successful reset.

## 4. Project and team management

### Q20. Who creates a project?

A student creates the initial proposal and becomes the project leader.

### Q21. What is the project approval flow?

The project starts as a draft, is submitted for approval, and can be approved, rejected, or returned for revision by an administrator.

### Q22. What is a project member?

A project member is a student associated with the project. One member has the leader role and other students have the member role.

### Q23. What is a primary guide?

The primary guide is the main faculty evaluator for a project. The system allows only one active primary guide per project through application workflow checks.

### Q24. Why are department and batch stored?

They scope academic organization, student eligibility, project ownership, and stage deadline configuration.

## 5. Stages, deadlines, and submissions

### Q25. What is a submission stage?

A stage is a project milestone such as Synopsis, SRS, Design, Implementation, Final Report, or Viva.

### Q26. How are deadlines selected?

The system uses a batch-specific deadline when available; otherwise it falls back to the stage’s default dates.

### Q27. What happens before or after a deadline?

Normal submissions are rejected before the opening date and after the deadline. The application compares dates using UTC.

### Q28. Who can submit a stage?

Only the student leader of an approved or active project can submit stage work.

### Q29. What file types are accepted?

PDF, DOCX, and ZIP files are accepted after extension, MIME type, size, and upload validation.

### Q30. How are uploaded files protected?

Files receive random stored names, are kept under private storage, and are served only after authorization checks.

### Q31. What is submission versioning?

Each project and stage can have multiple versions. A new version is created for resubmission or an approved special revision.

### Q32. What is a special revision?

It is one final revision created after an administrator approves an appeal. A special revision cannot itself be appealed.

## 6. Rubrics and evaluation

### Q33. What is a rubric?

A rubric is a marking guide containing criteria, maximum marks, and percentage weights for a stage.

### Q34. Why must rubric weights total 100%?

So the weighted result represents a complete percentage and no marks are unintentionally over-counted or under-counted.

### Q35. How is the percentage calculated?

For each criterion, the score is divided by its maximum and multiplied by its weight. The results are added to produce the final percentage.

### Q36. Can faculty give more marks than the maximum?

No. The application rejects negative marks and marks above each criterion maximum.

### Q37. What does finalization mean?

Finalization means the evaluation is officially submitted. Its scores and summary cannot be changed through normal database operations.

### Q38. Why are evaluation snapshots stored?

Criterion name, maximum marks, and weight are copied into the evaluation so later rubric edits do not change historical results.

### Q39. How is the grade selected?

The calculated percentage is compared with the configured grade boundaries, producing grades such as A+, A, B+, B, C, or F.

## 7. Appeals and feedback

### Q40. Who can file an appeal?

Only the project leader can appeal a rejected standard submission, and only the latest rejected version is eligible.

### Q41. Why cannot a special revision be appealed?

It is already the final exceptional revision granted by an approved appeal, so allowing another appeal would create an unlimited revision loop.

### Q42. Who decides an appeal?

An administrator reviews the appeal and either approves or rejects it with a decision reason.

### Q43. What happens when an appeal is approved?

A new special-revision draft is created, linked to the appeal, and made available to the project leader.

### Q44. How does feedback threading work?

A feedback message can be top-level or a reply. Replies are validated so their parent belongs to the same submission.

### Q45. What are notifications used for?

Notifications inform users about assignments, evaluations, appeals, feedback, status changes, and project completion.

## 8. Security

### Q46. What is CSRF protection?

Each form contains a session-bound random token. The server rejects POST requests with a missing or incorrect token.

### Q47. How is SQL injection prevented?

User input is passed through PDO prepared statements and bound parameters instead of being concatenated into SQL.

### Q48. How is XSS prevented?

Displayed user content is escaped with HTML encoding before rendering.

### Q49. How are private downloads protected?

The server checks the file record, resolves the stored path safely, and verifies that the logged-in user has access to the project.

### Q50. What security headers are used?

The application sends headers including Content Security Policy, frame denial, MIME sniffing protection, Referrer Policy, Permissions Policy, cache control, and HSTS when HTTPS is active.

### Q51. Why should configuration files not be committed?

They can contain database passwords, mail settings, or deployment secrets. Only example templates belong in GitHub.

## 9. Database and reliability

### Q52. Why use foreign keys?

Foreign keys preserve relationships and prevent references to missing departments, users, projects, submissions, or evaluations.

### Q53. Why use transactions?

Transactions ensure related changes, such as creating an evaluation and its scores, either all succeed or all roll back.

### Q54. What is status history?

It is a timeline of workflow state changes for projects, submissions, evaluations, appeals, guides, users, and configuration records.

### Q55. What are audit logs?

Audit logs record important administrative mutations with the actor, action, entity, old values, new values, IP address, and user agent.

### Q56. Why use separate database accounts?

The migration account needs DDL privileges, while the runtime account should have only the minimum permissions required by the application.

### Q57. What happens if the database is unavailable?

The application logs the technical error while showing a safe generic error message without exposing credentials or SQL details.

## 10. Deployment and limitations

### Q58. Which installer is used for Oracle MySQL?

Use [`database/install.sql`](./database/install.sql) with Oracle MySQL 8.4.

### Q59. Which installer is used for InfinityFree?

Use [`database/install-infinityfree.sql`](./database/install-infinityfree.sql), because InfinityFree may use MariaDB-compatible settings and does not permit the normal triggers.

### Q60. Why is InfinityFree not ideal for production?

The free plan may lack trigger support, reliable email delivery, private storage outside the web account, and enterprise backup guarantees.

### Q61. Why is the development seed unsafe for production?

It contains a known development account and password. Production must use a unique administrator password and a controlled provisioning process.

### Q62. What is the web root?

The web root should be `public/`, so application code, configuration, SQL files, and storage are not directly exposed.

### Q63. What are the current limitations?

The project has no external SMTP library, no deployment-specific server configuration, and hosting providers may impose storage, email, database, or trigger restrictions.

### Q64. What would you improve next?

Add automated browser tests, CI checks, a maintained SMTP adapter, stronger deployment automation, monitoring, scheduled cleanup, and a managed production database.

## 11. Best final viva answer

> “This portal centralizes academic project management for administrators, faculty, and students. It provides role-based access, project approval, team management, staged private submissions, rubric-based evaluation, appeals, feedback, notifications, and final sign-off. Security is handled using bcrypt, prepared statements, CSRF protection, secure sessions, upload validation, authorization checks, rate limiting, audit logs, and UTC-based deadlines. The main production limitations are hosting compatibility, trigger support, email transport, and private storage, so the deployment environment must be verified before real data is used.”

# MVP QA Checklist

Use dummy data. For each test, record the expected result, actual result, pass/fail status, and screenshot or log evidence.

## Test preparation

- [ ] Create administrator, faculty, student leader, student member, and inactive-user accounts.
- [ ] Create at least two departments and two batches.
- [ ] Create stages with valid and invalid dates.
- [ ] Create active rubrics with totals of 100%.
- [ ] Prepare valid PDF, DOCX, ZIP, oversized, empty, renamed, and unsupported files.
- [ ] Keep production credentials and real student data out of testing.

## A. Authentication and account management

- [ ] Valid admin, faculty, and student login succeeds.
- [ ] Invalid email and invalid password fail without account disclosure.
- [ ] Logout prevents access to protected pages.
- [ ] Temporary-password users are forced to change passwords.
- [ ] Password policy rejects short, weak, oversized, and null-byte passwords.
- [ ] New password and confirmation must match.
- [ ] Old password cannot be reused.
- [ ] Inactive users cannot log in.
- [ ] Admin can create admin, faculty, and student accounts.
- [ ] Students must have an active batch.
- [ ] Accounts require an active department.
- [ ] Duplicate email and registration numbers are rejected.
- [ ] Admin can activate and deactivate accounts.
- [ ] Self-deactivation is rejected.
- [ ] Password recovery gives the same response for existing and unknown emails.
- [ ] Valid reset links work once and expire after the configured period.
- [ ] Used reset links cannot be reused.

## B. Administrator configuration

- [ ] Create and update departments.
- [ ] Create and update batches.
- [ ] Create and update submission stages.
- [ ] Reject invalid or reversed stage dates.
- [ ] Create and update batch-specific deadlines.
- [ ] Reject invalid deadline ranges.
- [ ] Create rubric criteria with maximum marks and weights.
- [ ] Reject empty rubrics.
- [ ] Reject weights below zero or above 100.
- [ ] Reject a rubric whose active weights do not total 100%.
- [ ] Activate a stage only when its rubric is valid.
- [ ] Deactivate an active stage.
- [ ] Confirm all administrative changes create audit entries.

## C. Project and team workflow

- [ ] Student creates a project proposal.
- [ ] Project creator is the initial team leader.
- [ ] Student adds an eligible team member.
- [ ] Duplicate members are rejected.
- [ ] A member cannot be added to an unauthorized project.
- [ ] Leader can remove an eligible member.
- [ ] Proposal can be submitted for approval.
- [ ] Admin can approve a proposal.
- [ ] Admin can reject a proposal with remarks.
- [ ] Student can revise and resubmit a rejected proposal.
- [ ] Admin can assign a primary guide.
- [ ] Admin can reassign the primary guide.
- [ ] Only one active primary guide exists for a project.
- [ ] A guide from the wrong department is rejected.
- [ ] Project status history is recorded for each transition.

## D. Submission workflow

- [ ] Only an approved project can submit stage work.
- [ ] Only the project leader can submit work.
- [ ] Submission is rejected before the stage start date.
- [ ] Submission is rejected after the deadline.
- [ ] Submission succeeds during the valid window.
- [ ] Valid PDF, DOCX, and ZIP files are accepted.
- [ ] Files above 9 MiB are rejected.
- [ ] Empty, unsupported, or MIME-mismatched files are rejected.
- [ ] Stored filenames are random and do not expose original paths.
- [ ] Submission versions increase correctly.
- [ ] Revision-required submissions can be resubmitted.
- [ ] Special-revision drafts can be submitted once.
- [ ] A submitted special revision cannot be appealed.
- [ ] Submission status history is recorded.

## E. Faculty evaluation

- [ ] Faculty sees only projects assigned to that faculty member.
- [ ] Faculty cannot evaluate an unassigned project.
- [ ] Faculty can view authorized submissions and files.
- [ ] Missing rubric marks are rejected.
- [ ] Negative marks are rejected.
- [ ] Marks above the criterion maximum are rejected.
- [ ] Active rubric weights must total 100%.
- [ ] Weighted percentage is calculated correctly.
- [ ] Grade boundaries are applied correctly.
- [ ] Evaluation comments are saved.
- [ ] Approved, rejected, and revision-required decisions work.
- [ ] Special revisions cannot receive `revision_required`.
- [ ] Evaluation is finalized after saving scores.
- [ ] Finalized evaluations cannot be edited.
- [ ] Finalized score rows cannot be inserted, updated, or deleted.
- [ ] Students receive evaluation notifications.

## F. Appeals, feedback, and notifications

- [ ] Only the team leader can appeal.
- [ ] Only a rejected standard submission can be appealed.
- [ ] Only the latest rejected version can be appealed.
- [ ] Duplicate appeals are rejected.
- [ ] Admin can approve an appeal.
- [ ] Admin can reject an appeal.
- [ ] Approved appeals create exactly one special-revision draft.
- [ ] Appeal status and decision are recorded.
- [ ] Students can post feedback on accessible submissions.
- [ ] Faculty can reply to student feedback.
- [ ] Replies must belong to the same submission thread.
- [ ] Users receive relevant notifications.
- [ ] A notification can be marked read.
- [ ] All notifications can be marked read.
- [ ] Notification links remain local and safe.

## G. Project completion

- [ ] Only an authorized guide or administrator can complete a project.
- [ ] Required stages must be approved before sign-off.
- [ ] Incomplete projects cannot be signed off.
- [ ] Completion remarks are saved.
- [ ] Completion timestamp and actor are saved.
- [ ] Completion notification is sent to project members.
- [ ] Completed projects cannot be changed through normal workflow actions.

## H. Authorization and security

- [ ] Missing or invalid CSRF tokens are rejected.
- [ ] SQL injection strings are treated as data.
- [ ] HTML and script payloads are escaped.
- [ ] Students cannot access admin pages.
- [ ] Students cannot access other teams' projects.
- [ ] Faculty cannot access unrelated projects.
- [ ] Users cannot download another user's private file.
- [ ] Path traversal download attempts fail.
- [ ] `config/` files are not publicly readable.
- [ ] `storage/uploads/` files are not directly browsable.
- [ ] Login rate limits activate after repeated failures.
- [ ] Idle session timeout works.
- [ ] Absolute session timeout works.
- [ ] Password changes revoke previous sessions.
- [ ] Account deactivation revokes existing sessions.
- [ ] Security headers are present.
- [ ] HTTPS is used for production reset links.

## I. Database and reliability

- [ ] Foreign keys reject invalid references.
- [ ] Unique constraints reject duplicate records.
- [ ] Date checks reject invalid ranges.
- [ ] Score checks reject invalid marks.
- [ ] Status history records human actors correctly.
- [ ] Audit logs record administrative mutations.
- [ ] Transactions roll back partial changes after an error.
- [ ] Runtime database account has no DDL privileges.
- [ ] Database outage produces a safe error without leaking details.
- [ ] Mail failure does not expose account existence.
- [ ] Upload-storage failure produces a safe error.
- [ ] Backup and restore are tested with a disposable database.

## J. Deployment

- [ ] `install.sql` is used only with Oracle MySQL 8.4.
- [ ] `install-infinityfree.sql` is used for InfinityFree compatibility.
- [ ] `reset.sql` is never used in production.
- [ ] `seed-dev.sql` is never used in production.
- [ ] Runtime config files are ignored by Git.
- [ ] Only `public/` is exposed on a normal web server.
- [ ] HTTPS is enabled.
- [ ] Upload limits match the hosting provider.
- [ ] PHP, `pdo_mysql`, and `fileinfo` versions are verified.
- [ ] Logs do not contain passwords or reset tokens.

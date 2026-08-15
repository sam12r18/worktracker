# WorkTracker 0.1.0-alpha.7.2 — Admin Management + Context Help Hotfix

## Web

- dedicated Projects management surface;
- dedicated Customers management surface;
- customer assignment during Project creation/edit;
- project parent/status/color/archive controls;
- project pricing/history and Rule management;
- Task CRUD on Project detail;
- Activity Type active/sort/history improvements;
- Pricing Override update + expire workflow;
- dedicated API & Token page with Device Token workflow explanation;
- shared contextual `!` help modal across WorkTracker web pages;
- Blade layout kept structurally readable; no aggressive directive minification.

## Windows Agent

- build script stops a repository-run Agent before replacing the Release executable;
- single-instance mutex prevents accidental double capture;
- WPF `MessageBox` alias explicitly resolves `System.Windows.MessageBox`, fixing CS0104 when WinForms is also referenced for System Tray.
- Project Rule operators are now persisted locally and honored by the classifier (`contains`, `equals`, `starts_with`, `ends_with`, `regex`).

## Database

No new migration is required for these changes; they use existing Project, Customer, ProjectRule, Task and pricing-history schema.

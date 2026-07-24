# تنظیم یک‌باره GitHub برای توسعه خودکار

پس از ایجاد مخزن `rahbarbakhtiari-collab/tazkar`:

1. Auto-merge را در Repository Settings فعال کنید.
2. Branch protection برای `main`:
   - Require pull request;
   - Require status checks;
   - Require branch up to date;
   - Do not allow force push;
   - Do not allow deletion.
3. checkهای اجباری:
   - `PR Policy / validate`
   - تمام jobهای `CI / quality`
   - `CI / wordpress-smoke`
4. Labelها را بسازید:
   - `agent-ready`
   - `agent-in-progress`
   - `automerge`
   - `blocked`
   - `human-decision-required`
   - `risk-low`
   - `risk-medium`
   - `risk-high`
5. repository را به Codex Environment متصل کنید.
6. Automatic Code Review را فعال کنید.
7. Automationهای Issue Dispatcher و CI Repair را با promptهای `ORCHESTRATOR-SETUP.md` ایجاد کنید.

تا انجام این تنظیمات، workflowها در repository وجود دارند ولی حلقه توسعه کاملاً unattended نیست.

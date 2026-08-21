# Putting EduNest on GitHub

Right now the repository exists **only on this laptop**. Four weeks of work, and
one hard disk failure or one bad `git reset` would end it. This is the most
valuable hour you can spend on the project.

---

## Before you start

Two things have already been checked, so you can push safely:

- **`.env` has never been committed.** It is listed in `.gitignore` and does not
  appear anywhere in the history. Your database password and application key are
  not going to GitHub.
- **`vendor/` and `node_modules/` are ignored.** Those are rebuilt with
  `composer install` and `npm install`, so there is no reason to store them.

---

## 1. Create the repository

1. Go to <https://github.com/new>
2. **Repository name:** `edunest`
3. **Description:** Multi-tenant school management and school website platform
4. **Visibility: Private.** This matters. The repository will contain the
   structure of a system holding real children's records. Make it public only
   after a deliberate decision.
5. **Do not** tick "Add a README", "Add .gitignore" or "Choose a license".
   The project already has its own, and pre-filling them creates a conflict you
   would then have to untangle.
6. Click **Create repository**

---

## 2. Connect this project to it

GitHub will show you a URL like `https://github.com/YOURNAME/edunest.git`.

Open a terminal in the project folder and run:

```powershell
cd C:\Users\OlajideCares\Documents\EduNest

git remote add origin https://github.com/YOURNAME/edunest.git

git remote -v
```

That last command should print your URL twice, once for `fetch` and once for
`push`. If it does, the connection is set up.

> If you get `error: remote origin already exists`, a remote is already
> configured. Check where it points with `git remote -v`, and change it with
> `git remote set-url origin <the correct url>`.

---

## 3. Push both branches

```powershell
git push -u origin main
git push -u origin dev
```

The first push will ask you to sign in to GitHub. A browser window opens; sign
in there and it will remember you afterwards.

`-u` links your local branch to the GitHub one, so from then on you can just
type `git push`.

---

## 4. Make `main` the protected branch

`main` is production. Nothing should land there without passing through `dev`
first.

On GitHub: **Settings → Branches → Add branch protection rule**

- Branch name pattern: `main`
- Tick **Require a pull request before merging**
- Once continuous integration is set up, also tick **Require status checks to
  pass before merging**

---

## How to work day to day

```
dev   ← you work here
 │
 │  when a feature is finished and its tests pass
 ▼
main  ← only ever receives finished, tested work
```

**Start each session** on `dev`:

```powershell
git switch dev
git status          # check nothing is half-finished from last time
```

**Save your work** as you go. Commit whenever one thing is done, not once a
week:

```powershell
git add -A
git commit -m "feat: add attendance summary to the parent dashboard"
git push
```

**When a feature is finished and tested**, move it to `main`:

```powershell
git switch main
git merge dev
git push
git switch dev      # go straight back to dev so you never work on main
```

---

## Writing commit messages

Start with a type, then a colon, then what changed in plain words:

| Type | Use it for |
| ---- | ---------- |
| `feat:` | A new feature |
| `fix:` | Fixing something broken |
| `security:` | A security improvement |
| `refactor:` | Rewriting code without changing behaviour |
| `test:` | Adding or changing tests |
| `docs:` | Documentation only |
| `chore:` | Dependencies, configuration, tooling |

Good:

```
feat: add attendance summary to the parent dashboard
fix: correct term average when a subject has no exam score
security: rate limit password reset requests per IP address
```

Not useful:

```
update
changes
fixed stuff
asdf
```

The reason to care: in a year you will be looking for the change that broke
something. `git log --oneline` is only useful if the messages are.

---

## Things worth knowing

**Never commit `.env`.** If it ever happens, changing the file later is not
enough — the old version stays in the history forever. You would need to rewrite
history *and* change every password and key it contained.

**Committing is not backing up.** A commit only saves to this laptop. `git push`
is what sends it to GitHub. Push at the end of every session.

**`git status` is free.** Run it whenever you are unsure. It tells you which
branch you are on and what has changed.

**Never use `git reset --hard` unless you are certain.** It permanently discards
uncommitted work. If you are not sure, commit first — a messy commit can always
be tidied up later, but discarded work is gone.

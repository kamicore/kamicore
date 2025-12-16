# KamiCore Roadmap

This roadmap reflects the current **state and direction** of the project,
not a strict development plan or timeline.

KamiCore is developed iteratively and non-linearly,
based on practical needs, experimentation, and architectural decisions.

---

## 🧩 Core & Infrastructure
**Status:** in progress

- Request lifecycle and entry points (web / api / ajax)
- Environment initialization
- Domain resolution
- Routing and request dispatching
- Plugin loading and execution order

The core is already functional for real use cases,
but internal APIs and execution flow may still evolve.

---

## 📦 Content Model
**Status:** partially implemented

- Content types
- Content items
- Field definitions
- Basic field variants and validation
- JSON-based data storage

Core content concepts are implemented and actively used.
Some field variants, edge cases, and validations are still missing or incomplete.

---

## 🌍 Translations & Localization
**Status:** structure ready

- Translation storage schema
- Entity-to-translation relationships
- Language resolution logic

The data model is in place.
Management UI and helper wrappers are still under development.

---

## 🔐 Users & Permissions
**Status:** partially implemented

- User accounts
- User groups
- Domain-based access scope
- Basic permission checks

Permission rules, inheritance, and enforcement logic
are not finalized yet and may change.

---

## 🧠 Plugins & Extensions
**Status:** active development

- Plugin-first architecture
- Direct plugin invocation
- Plugin-defined data structures
- Plugin-controlled rendering output

There is intentionally no global hook system executed on every request.

---

## 🖥️ Admin UI
**Status:** early but functional

- Admin UI implemented as a plugin
- Basic content management
- Minimal navigation and structure editing

The admin interface works for internal use
but is not considered stable or complete.

---

## 🧭 Pages & Navigation
**Status:** functional (minimal)

- Page structure
- Navigation menus
- Plugin-based page composition

The system is usable but intentionally minimal at this stage.

---

## 🧰 Tooling & Developer Experience
**Status:** experimental / planned

- Developer utilities
- Debug and diagnostic tools
- Logging and introspection

These areas will evolve alongside the core.

---

## 📚 Documentation
**Status:** early stage

- High-level architecture overview
- Core concepts and terminology
- Design decisions and constraints

Documentation is being written incrementally
as the system stabilizes.

---

## 🗺️ Future directions (non-binding)

- Reference plugins
- Improved admin UX
- Extended content field variants
- API-first workflows
- Performance profiling and optimization

These directions are indicative and may change
based on real-world usage and feedback.

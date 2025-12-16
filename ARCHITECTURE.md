# KamiCore Architecture

This document describes the high-level architectural principles
and design direction of the KamiCore project.

## Overview

KamiCore is a minimal, modular core designed to serve as a foundation
for content-driven systems and web services.

The project follows a **plugin-first** approach:
the core provides only essential infrastructure,
while all higher-level functionality is implemented as plugins.

## Core responsibilities

The KamiCore core is responsible for:

- request initialization
- environment and domain resolution
- routing and request dispatching
- security boundaries and access checks
- plugin loading and execution order

The core does **not**:

- implement business logic
- contain UI or admin interfaces
- bundle optional features by default

## Plugin model

Plugins are first-class citizens in KamiCore.

A plugin may:

- provide frontend output
- expose API endpoints
- define its own data structures
- integrate with other plugins through explicit interfaces

Plugins are loaded explicitly and executed only when required.
There is no global hook system executed on every request.

## Rendering pipeline

A typical request flow:

1. Request entry point
2. Core initialization
3. Domain and context resolution
4. Routing
5. Plugin execution
6. Rendering (theme / template layer)
7. Response output

Rendering is compositional: the final response may consist of
multiple plugin outputs assembled by the core.

## Design goals

- predictability over magic
- explicit data flow
- minimal global state
- performance-aware architecture
- long-term maintainability

---

This document will evolve alongside the source code.

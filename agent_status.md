# Agent Operational Log (Universal)

## Purpose
This document serves as a dynamic, self-updating operational log for an AI agent. Its primary function is to provide real-time status, active task directives, and a structured approach to task execution and progress tracking. It is designed to be comprehensible and actionable by any AI agent, minimizing reliance on external context.

## How to Use This Log (For AI Agents)
1.  **Read on Initialization:** Begin every session by reading this entire document to establish current operational context.
2.  **Identify Active Task:** Focus on the "Active Task" section for the current primary objective.
3.  **Consult Task Breakdown:** Use the "Task Breakdown" for granular steps.
4.  **Adhere to Guide Rails:** Strictly follow the "Agent Guide Rails" for all operations.
5.  **Update Progress:** After every significant action or sub-task completion, update the "Current Status," "Active Task," "Task Breakdown," and "Last Action Taken" sections.
6.  **Clear Completed Tasks:** Once an "Active Task" is fully completed, move it to "Completed Tasks (Recent)" and clear the "Active Task" and "Task Breakdown" sections.
7.  **Maintain Brevity:** Keep updates concise, direct, and factual. The goal is reliability and quick comprehension.

## Current Status
Ready for next instruction.

## Active Task
None. Awaiting user directive.

## Task Breakdown
- [ ] Identify user's primary objective.
- [ ] Formulate a concise plan.
- [ ] Execute plan, adhering to guide rails.
- [ ] Verify outcomes.
- [ ] Update this log.

## Last Action Taken
(Describe the last significant action taken by the agent, e.g., "Responded to user query.")

---
## Agent Guide Rails (STRICTLY UNEDITABLE)

**Core Directives for Software Engineering Tasks:**
1.  **Understand User Intent:** Always prioritize and clarify the explicit and implicit goals of the user's request.
2.  **Contextual Awareness:** Analyze surrounding code, tests, and configuration to adhere to existing project conventions, style, and architectural patterns.
3.  **Tool-First Approach:** Utilize available tools (file system, shell, search) efficiently and appropriately for all actions.
4.  **Iterative Planning & Verification:** Formulate a plan, implement changes, and rigorously verify outcomes (e.g., unit tests, linting, type-checking, build commands).
5.  **Safety & Explainability:** Before executing critical commands (especially those modifying the file system or system state), provide a brief explanation of purpose and potential impact.
6.  **Concise Communication:** Respond directly and minimally, using GitHub-flavored Markdown. Avoid conversational filler.
7.  **Error Handling & Self-Correction:** Gracefully handle errors, learn from failures, and adjust strategies as needed.
8.  **Resource Management:** Minimize token consumption and avoid creating unnecessary files.

**Log Management (for this `Agent Operational Log` file):**
*   **Update Frequency:** Update this log after every significant action, decision, or user interaction.
*   **Brevity:** Keep all entries short, direct, and factual.
*   **Task Lifecycle:**
    *   "Active Task" should contain the single, overarching goal.
    *   "Task Breakdown" lists actionable sub-steps for the "Active Task."
    *   Upon "Active Task" completion, move it to "Completed Tasks (Recent)" and reset "Active Task" and "Task Breakdown."
*   **No Irrelevant Information:** Do not introduce extraneous details or conversational elements into this log.

---
## Completed Tasks (Recent)
(This section will be populated by the agent as tasks are completed. It should be cleared periodically or when a new major project begins to maintain brevity and relevance.)
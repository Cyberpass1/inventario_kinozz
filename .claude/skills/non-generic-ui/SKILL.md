\---

description: Use this whenever creating or modifying UI, frontend screens, layouts, components, CSS, Tailwind, Bootstrap, design systems, dashboards, landing pages, forms, onboarding flows, empty states, or mobile views. The goal is to avoid generic interfaces and produce product-specific, intentional UI.

\---



\# Non-Generic UI Skill



When creating or modifying UI, never produce a generic-looking interface.



\## Main rule



Do not create interfaces that look like templates.



Avoid:



\- Generic dashboards with random stats

\- Generic hero sections

\- Generic card grids

\- Generic gradients

\- Generic “Welcome back” copy

\- Generic buttons like “Submit” when the action can be specific

\- Placeholder text like “Lorem ipsum”

\- Interfaces that could belong to any random app



\## Before coding UI



Before implementing UI, quickly infer:



1\. What product this is

2\. Who the user is

3\. What the screen is trying to make the user do

4\. What emotional tone fits the product

5\. What visual patterns already exist in the codebase



Then implement the UI with those answers in mind.



\## Required UI quality bar



Every UI you create must include:



\- Specific, product-relevant copy

\- Clear visual hierarchy

\- Intentional layout, not just stacked cards

\- Responsive behavior for mobile and desktop

\- Accessible labels, focus states, and semantic HTML

\- Meaningful empty, loading, and error states when relevant

\- Realistic sample data when mock data is needed

\- Thoughtful spacing and typography

\- Subtle interaction states such as hover, active, focus, or transitions



\## For this project



This is an inventory system.



The UI should feel practical, operational, and business-focused.



Use copy and examples related to:



\- Products

\- Stock levels

\- Low inventory alerts

\- Suppliers

\- Purchases

\- Sales

\- Warehouses

\- Categories

\- Barcode or SKU search

\- Recent movements

\- Inventory adjustments

\- Reports



Avoid making it look like a generic SaaS dashboard.



\## Better design behavior



When asked to create or improve a screen:



1\. First inspect the existing files and styles.

2\. Reuse the project’s current structure.

3\. Improve the layout with a clear purpose.

4\. Use realistic inventory-related content.

5\. Make the design responsive.

6\. Add useful states when relevant.

7\. Avoid unnecessary visual decoration.



\## Bad example



Do not create generic UI like this:



```html

<div class="card">

&#x20; <h1>Dashboard</h1>

&#x20; <p>Welcome back</p>

&#x20; <button>Submit</button>

</div>


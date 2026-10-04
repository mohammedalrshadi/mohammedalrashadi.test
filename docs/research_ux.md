# UX Research & Design Principles

## 1. Product Detail Page Hierarchy (Baymard / NN/g)
- **Top level (Hero):** Users expect the visual (Gallery) on the left and the critical purchase/download information on the right.
- **Scannability:** Users rarely read long descriptions first. They scan for "What is this?", "What do I get?", and "How much does it cost?".
- **Clear CTAs:** Baymard explicitly warns against vague CTAs ("Submit", "Continue"). Action-driven CTAs ("Buy on Gumroad", "Download Free") increase trust and clarity.

## 2. Digital Product Specifics (Gumroad / Stripe UX)
- **Format Transparency:** Digital product pages must explicitly state the file format (PDF, ZIP, Figma). This reduces post-purchase anxiety.
- **Security Boundaries:** If checkout happens externally, the UI must honest about it. Pretending to be an internal checkout when it redirects to ThemeForest breaks trust.

## 3. Data-Driven Mapping (Mohammed Alrashadi Store)
- **Free Product:** `price = 0` or `type = free_download`. Action: `Download`.
- **Paid External:** `external_url` exists. Action: `Get on [Platform]`.
- **Missing Destination:** If no download and no external URL, the CTA must be hidden or disabled to prevent broken loops.

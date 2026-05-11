# Copyright Registration Filing Guide — HackTrader

A worksheet for filing a U.S. Copyright Registration on the HackTrader codebase via the U.S. Copyright Office's electronic filing system (eCO) at [copyright.gov](https://www.copyright.gov).

**Status:** Draft for filing. Have a registered patent or copyright practitioner review before submission. Filing is online; this document tells you what to enter.

---

## Why register

Copyright in original works of authorship is automatic upon creation under U.S. law. *Registration*, however, is what enables you to:

1. **Sue for infringement in federal court.** A registered copyright is a prerequisite to filing an infringement suit in the United States.
2. **Recover statutory damages and attorneys' fees.** Without registration, you can only recover actual damages (which are notoriously hard to prove for software). Registration enables statutory damages up to **$150,000 per willful infringement** and recovery of attorneys' fees.
3. **Establish prima facie validity.** A registration certificate is presumptive evidence in court that the copyright is valid and you are the owner.

Registration costs $45 (for a single author / single work / not-for-hire) or $65 (for most other configurations) and takes approximately 6–8 months to issue. The protection is *retroactive to the date of filing*, so filing now matters even if a future lawsuit is years away.

## What to register

The HackTrader codebase as a *computer program* — an integrated whole consisting of PHP, JavaScript (embedded in PHP), Python, and supporting configuration files. The visualization output rendered by the code is *not* the registered work; the source code is.

Filing covers the version of the codebase as of the deposit date. New versions can be registered separately (or as a "supplementary registration" if substantial new authorship has been added).

## What to file at copyright.gov

### Step 1 — Create an account

At [eco.copyright.gov](https://eco.copyright.gov), create an account if you don't already have one. Use a Penguins LLC business email if available, or a personal email you'll keep long-term. The account is what receives the registration certificate.

### Step 2 — Choose application type

Select: **"Standard Application"** (works with one or more authors, or work made for hire).

Do NOT select "Single Application" — that's only for one work, by one author, who is also the sole owner, and is not work made for hire. Since Penguins LLC will be the owner (post-assignment) but you are the author, Standard is correct.

### Step 3 — Type of work

Select: **"Work of the Performing Arts"** ❌ NO

Select: **"Literary Work"** ❌ NO

Select: **"Work of the Visual Arts"** ❌ NO

Select: **"Sound Recording"** ❌ NO

Select: **"Motion Picture / AV Work"** ❌ NO

Select: ✅ **"Computer Program"** under Other Digital Content / Software.

In the eCO interface this typically appears as: *Type of Work → Literary Work → Computer Program.* (eCO classifies software under literary works for filing purposes; this is correct.)

### Step 4 — Title information

| Field | Value |
|---|---|
| Title of this work | `HackTrader` |
| Title type | Title of work being registered |
| Previous or alternative titles | (none — leave blank if this is the first registration) |
| Year of completion | `2026` |

### Step 5 — Publication information

This is the trickiest field. "Publication" in copyright law has a specific meaning: making the work available to the public *with the consent of the owner* by sale, distribution, public display, etc. Note that *deploying software to a SaaS server users authenticate to* is a debated edge case — many practitioners treat SaaS deployment as publication; some don't.

**Conservative recommendation:** treat the work as **published** as of the date you launched `dev.hacktrader.com` and made it available to authenticated users. Pick a clear date.

| Field | Value |
|---|---|
| Has this work been published? | Yes |
| Date of first publication | `[the date you first allowed external users to access dev.hacktrader.com]` |
| Nation of first publication | United States |

If you genuinely cannot determine a publication date and the codebase has only ever been accessed by you, file as **Unpublished**. Unpublished works enjoy slightly different procedural rules but otherwise the same protection.

### Step 6 — Author information

| Field | Value |
|---|---|
| Author name | `Jayson Hawley` |
| Author type | Individual |
| Year of birth | (your year of birth — required by the Copyright Office) |
| Year of death | (leave blank, you are alive) |
| Citizen of | United States |
| Domiciled in | United States |
| Anonymous? | No |
| Pseudonymous? | No |
| Author created | Computer program (text) |
| Was this work made for hire? | **No** |

The "made for hire" question matters. If you check **Yes**, the *employer* (Penguins LLC) is the author, not you. **You should check No** because the code was authored by you personally before the LLC's formal involvement in development. The IP assignment document (filed separately) is what transfers ownership from you to Penguins LLC; copyright authorship still resides with you.

### Step 7 — Copyright claimant

The "claimant" is the *current owner* of the copyright at the time of registration.

If you have already executed the IP Assignment Agreement (Penguins LLC is the assignee):

| Field | Value |
|---|---|
| Claimant name | `Penguins LLC` |
| Claimant address | `999 Corporate Drive, Ste 100, Ladera Ranch, CA 92694` |
| Transfer statement | "By written assignment from Jayson Hawley dated [assignment date]" |

If you have NOT yet executed the assignment, the claimant is you personally:

| Field | Value |
|---|---|
| Claimant name | `Jayson Hawley` |
| Claimant address | (your address) |
| Transfer statement | (leave blank) |

You can later record the assignment with the Copyright Office to update the public record. Don't let this question delay registration.

### Step 8 — Limitation of claim

Most software registrations include some pre-existing material (open-source libraries, etc.). You can either disclaim those (recommended) or claim the entire work.

**For HackTrader's codebase:**

Material excluded from this claim:
- Stripe PHP SDK (`vendor/stripe/`)
- Composer dependencies (`vendor/`)
- Google Fonts CSS (referenced via CDN, not embedded)
- Google API client libraries
- Lightweight Charts library (referenced via CDN)
- `phpstan.phar`, `phpunit.phar`, `ruff` binaries (build tooling)

New material in this claim: All HackTrader-specific PHP source, JavaScript (embedded), Python source, CSS, HTML structure, configuration files (`correlations.json`, `lib/plans.php`), and documentation.

The eCO form has a free-text field for this; describe it as: "Original computer program code, excluding third-party open-source libraries (Stripe SDK, Composer dependencies, Google API libraries, charting libraries) and dev tooling binaries."

### Step 9 — Rights and permissions contact

The contact person for licensing inquiries.

| Field | Value |
|---|---|
| Name | `Jayson Hawley` |
| Organization | `Penguins LLC` |
| Email | `info@pngs.us` |
| Phone | `(949) 335-9688` |
| Address | `999 Corporate Drive, Ste 100, Ladera Ranch, CA 92694` |

### Step 10 — Correspondence contact

Same as Rights and Permissions, unless you want correspondence to come to a different address.

### Step 11 — Mail certificate to

Same as above. The certificate is mailed to the listed address once registration is complete.

### Step 12 — Special handling

Skip unless you need expedited review (e.g., for pending litigation). Special handling fees are $800 and serve no purpose for a routine filing.

### Step 13 — Certification

You sign certifying that you are the author or authorized agent and that the information is true and correct.

### Step 14 — Pay the fee

Standard application: **$65**.

Pay via credit card, debit card, ACH, or deposit account. Receipt is emailed instantly.

### Step 15 — Upload deposit copy

You must submit a "deposit" copy of the work — the actual code being registered.

For computer programs, the Copyright Office accepts:

- **First 25 and last 25 pages of source code** (recommended) — minimizes trade secret exposure while satisfying the deposit requirement; or
- **Entire source code** (not recommended for trade-secret reasons); or
- **Object code in machine-readable form**.

**Recommendation: Upload first-25 / last-25 pages.** Generate a PDF containing:

- The first 25 pages of source code from the most representative file (use `dashboard.php` or `run-brk.py` — pick the one most central to the invention)
- The last 25 pages of the same file

Or, more practically, take a representative cross-section of the codebase: 25 pages of the most important file's beginning, then 25 pages of another important file's ending.

**Practical tip:** the Copyright Office is satisfied with any reasonable interpretation of "first 25 / last 25." You can include marginal redactions of the most sensitive parts of the algorithm (e.g., specific magic numbers in `run-brk.py` like the asymmetry weights) to preserve trade secret protection. Use a black-box redaction marker.

PDF generation: From your Mac, `cat dashboard.php | enscript -B -o - | ps2pdf - dashboard-deposit.pdf` works, or use any IDE's "Export to PDF" function.

### Step 16 — Submit

Hit submit. The system issues a case number; the registration certificate arrives by mail in 6–8 months.

You may now mark your work with the standard copyright notice:

```
© 2026 Penguins LLC. All rights reserved.
HackTrader and the HackTrader correlation radar are protected
by U.S. copyright law and pending U.S. and foreign patents.
```

---

## After registration

1. **Update the footer copy on dev.hacktrader.com** to reflect the registration once the certificate arrives. Optionally include the registration number (e.g., "U.S. Copyright Reg. No. TXu2-345-678").
2. **File supplementary registrations** for major new versions (e.g., when v2.0 ships with substantial new authorship).
3. **Maintain a register of contributors.** If you ever take on contractors or contributors, get a written work-for-hire and assignment from each before they touch the codebase. Otherwise their authorship may complicate future registrations.

## Estimated total cost

| Item | Cost |
|---|---|
| Standard Application filing fee | $65 |
| Time | ~2 hours |
| **Total** | **$65** |

## Questions for the attorney

If you're consulting an IP attorney before filing:

1. Whether the SaaS deployment of `dev.hacktrader.com` constitutes "publication" for copyright purposes.
2. Whether to register `run-brk.py` separately as a different work, given its different authorship history.
3. Whether to include redactions in the deposit copy to preserve trade secret status.
4. Whether to record the IP Assignment Agreement with the Copyright Office (a separate filing once executed).

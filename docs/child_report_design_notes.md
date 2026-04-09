# Child Report Design Notes

This report flow is designed as a parent-facing support tool, not a diagnostic system.

## What the report now does

- Groups recent child-authored chat into broad wellbeing themes instead of exposing raw quotes.
- Flags higher-priority safety concerns such as self-harm language, harm-to-others language, secrecy around unsafe contact, and repeated distress themes.
- Separates `risk dimensions`, `thinking patterns`, `protective factors`, and `parent guidance`.
- Stores generated reports as snapshots so parents can review report history over time.
- Keeps each saved report tied to its own internally retained chat scope so later cumulative analysis can look across saved reports without exposing raw chat in the parent UI.
- Saves a new manual snapshot each time parents click generate and the current incremental threshold is met.
- Supports cumulative analysis across multiple saved reports for trend review.
- Supports auto-generation settings per child account and a background runner via `php generate_scheduled_reports.php`.

## Privacy rules

- The report UI shows paraphrases and pattern counts only.
- Raw messages are retained only in protected report-linked storage for internal report continuity and cumulative analysis, not shown in the parent UI.
- Structured LLM generation receives only aggregated signals and paraphrased summaries, not raw message text.

## Reference principles used

- AACAP guidance on suicide warning signs in youth:
  `https://www.aacap.org/AACAP/Families_and_Youth/Facts_for_Families/FFF-Guide/Teen-Suicide-010.aspx`
- CDC youth violence risk factors:
  `https://www.cdc.gov/youth-violence/risk-factors/index.html`
- HealthyChildren on regular mental health screening and watching for patterns over time:
  `https://www.healthychildren.org/English/health-issues/conditions/emotional-problems/Pages/how-regular-mental-health-screenings-for-children-can-make-a-difference.aspx`
- HealthyChildren on anxiety disorders in children and teens:
  `https://www.healthychildren.org/English/health-issues/conditions/emotional-problems/Pages/Anxiety-Disorders.aspx`
- UK ICO Children’s Code on data minimisation:
  `https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/childrens-information/childrens-code-guidance-and-resources/age-appropriate-design-a-code-of-practice-for-online-services/8-data-minimisation/`

## Operational note

For true background auto-generation, configure server cron to run:

```bash
php /home/wkd/AIChatforChildren/generate_scheduled_reports.php
```

Without cron, due reports are still generated when a parent opens the report workspace for that child.

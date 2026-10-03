/**
 * Engagement islands entry (loaded only on pages that contain an engagement component: the Blade
 * partials push it with @pushOnce). Each island root is preceded by
 * `<script type="application/json" data-engagement-props>{"island": …, "props": …}</script>`.
 */
import { readProps } from "./core";
import { enhanceCopyButtons, mountDonateWidget, type DonateWidgetProps } from "./donate-widget";
import { mountNewsletterForm, mountUnsubscribeForm, type NewsletterFormProps } from "./newsletter-form";
import { mountSubmissionForm, type SubmissionFormProps } from "./submission-form";

type Island =
  | { island: "submission"; props: SubmissionFormProps }
  | { island: "newsletter"; props: NewsletterFormProps }
  | { island: "unsubscribe"; props: { token: string } }
  | { island: "donate"; props: DonateWidgetProps };

const mounted = new WeakSet<HTMLScriptElement>();

function mountAll() {
  for (const script of document.querySelectorAll<HTMLScriptElement>("script[data-engagement-props]")) {
    const root = script.nextElementSibling;
    if (!root || mounted.has(script)) continue;
    const config = readProps<Island>(root);
    if (!config) continue;
    mounted.add(script);
    try {
      switch (config.island) {
        case "submission":
          mountSubmissionForm(root, config.props);
          break;
        case "newsletter":
          mountNewsletterForm(root, config.props);
          break;
        case "unsubscribe":
          mountUnsubscribeForm(root, config.props);
          break;
        case "donate":
          mountDonateWidget(root, config.props);
          break;
      }
    } catch (error) {
      console.error("[engagement] failed to mount", config.island, error);
    }
  }
  enhanceCopyButtons();
  // Signal for tests / analytics that the islands are interactive.
  document.documentElement.dataset.engagementReady = "1";
}

if (document.readyState === "loading")
  document.addEventListener("DOMContentLoaded", mountAll, { once: true });
else mountAll();

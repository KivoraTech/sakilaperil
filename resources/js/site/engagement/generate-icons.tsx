/**
 * Generates the exact SVG markup lucide-react renders for the icons the engagement components use,
 * for Blade (resources/js/site/engagement/icons.json, read by App\Engagement\View\Icons) and the
 * vanilla TS islands (icons.ts imports the same JSON). Re-run after upgrading lucide-react:
 *   npx tsx resources/js/site/engagement/generate-icons.tsx
 */
import { writeFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import {
  AlertCircle,
  AlertTriangle,
  Check,
  CheckCircle2,
  Copy,
  CreditCard,
  Download,
  FlaskConical,
  Heart,
  Landmark,
  Lock,
  Smartphone,
  Wallet,
  type LucideIcon,
} from "lucide-react";
import { createElement } from "react";
import { renderToStaticMarkup } from "react-dom/server";

const icons: Record<string, LucideIcon> = {
  AlertCircle,
  AlertTriangle,
  Check,
  CheckCircle2,
  Copy,
  CreditCard,
  Download,
  FlaskConical,
  Heart,
  Landmark,
  Lock,
  Smartphone,
  Wallet,
};

const MARK = "__CLASS__";
const out: Record<string, string> = {};
for (const [name, Icon] of Object.entries(icons)) {
  // Rendered exactly as the reference components do: <Icon aria-hidden="true" className="…" />.
  out[name] = renderToStaticMarkup(createElement(Icon, { "aria-hidden": "true", className: MARK }));
}
const target = resolve(dirname(fileURLToPath(import.meta.url)), "icons.json");
writeFileSync(target, `${JSON.stringify(out, null, 2)}\n`);
console.log(`wrote ${Object.keys(out).length} icons → ${target}`);

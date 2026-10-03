/**
 * Human labels and hints for generated block-form fields. Keys are field names from
 * lib/blocks/schemas.ts; unknown keys fall back to a humanised version of the name.
 */
const FIELD_LABELS: Record<string, { label: string; hint?: string }> = {
  kicker: {
    label: "Small text above the title",
    hint: "A few words, e.g. “Dal 2017” or “I nostri progetti”.",
  },
  heading: { label: "Heading" },
  headingAccent: {
    label: "Highlighted words in the title",
    hint: "Shown right after the heading in the accent colour, e.g. “famiglie”. Optional.",
  },
  subheading: { label: "Text under the title" },
  lead: { label: "Introduction", hint: "One or two sentences that open the section." },
  body: { label: "Text" },
  image: { label: "Image" },
  secondaryImage: { label: "Second image", hint: "Optional smaller photo that overlaps the main one." },
  sideImage: { label: "Image beside the text" },
  badgeText: { label: "Round badge text", hint: "Very short, e.g. “DAL 2017 · ITALIA ↔ NEPAL”. Optional." },
  primaryCta: { label: "Main button", hint: "The most important action on this section, e.g. “Dona ora”." },
  secondaryCta: { label: "Second button", hint: "Optional, less prominent action." },
  cta: { label: "Button" },
  highlightProjectId: {
    label: "Project to feature",
    hint: "Shows this project's fundraising progress on the big top section. Optional.",
  },
  quote: { label: "Quote" },
  quoteAccent: {
    label: "Highlighted words in the quote",
    hint: "Shown after the quote in the accent colour.",
  },
  author: { label: "Who said it" },
  role: { label: "Their role", hint: "e.g. “Volontaria, Milano” or “Mamma di Sita”." },
  imageCaption: { label: "Photo caption" },
  caption: { label: "Caption", hint: "Short text shown under the photo." },
  source: { label: "What to show" },
  albumId: { label: "Photo album" },
  images: { label: "Photos" },
  limit: { label: "How many to show" },
  projectIds: { label: "Projects", hint: "Only used when “Choose manually” is selected above." },
  endCard: { label: "Last card", hint: "Optional card at the end with a button, e.g. “See all projects”." },
  items: { label: "Items" },
  value: { label: "Number", hint: "Just the number, e.g. 1200." },
  suffix: { label: "After the number", hint: "e.g. “+” or “%”. Optional." },
  label: { label: "Label", hint: "What the number means, e.g. “children in school”." },
  progress: {
    label: "Progress bar fill",
    hint: "Optional. Use a decimal: 0.75 fills the bar to 75%, 1 fills it completely.",
  },
  name: { label: "Name" },
  question: { label: "Question" },
  answer: { label: "Answer" },
  categoryId: { label: "Only news from this category", hint: "Leave empty to show all news." },
  showAllLink: { label: "Show a “See all news” link" },
  gifts: { label: "What a gift does", hint: "Examples like “€10 = school notebooks for a year”." },
  amount: { label: "Amount (€)" },
  description: { label: "Description" },
  reasons: { label: "Reasons to give" },
  trustStats: { label: "Trust figures", hint: "Short facts that reassure donors, e.g. “100% to projects”." },
  showWidget: { label: "Show the donation form" },
  projectId: { label: "Donations go to this project", hint: "Leave empty to let donors choose." },
  members: { label: "People" },
  bio: { label: "A few words about them" },
  partners: { label: "Partners" },
  url: { label: "Website address", hint: "Starts with https://" },
  logo: { label: "Logo" },
  year: { label: "Year" },
  title: { label: "Title" },
  note: { label: "Small print", hint: "Optional note shown in small text." },
  showForm: { label: "Show the contact form" },
  showDetails: { label: "Show address, phone and email" },
  showMap: { label: "Show the map" },
  types: { label: "Kinds of documents", hint: "Leave all unticked to show every kind." },
  forms: { label: "Forms to show" },
  // settings
  anchor: {
    label: "Link name for this section (optional)",
    hint: "Lets buttons jump straight here. E.g. “dona” makes “#dona” links scroll to this section. Use lowercase letters, numbers and dashes.",
  },
  background: { label: "Background colour" },
  height: { label: "Height" },
  overlay: { label: "Darken the photo", hint: "Darker makes white text easier to read." },
  width: { label: "Width" },
  imagePosition: { label: "Photo side" },
  variant: { label: "Style" },
  tone: { label: "Colour" },
  layout: { label: "Layout" },
  size: { label: "Size" },
  divider: { label: "Show a divider line" },
  href: {
    label: "Link",
    hint: "A page of this site (e.g. /projects) or a full address starting with https://",
  },
  alt: {
    label: "Describe the photo (for blind visitors and Google)",
    hint: "What is in the picture, e.g. “Children reading in a classroom in Kathmandu”.",
  },
  assetId: { label: "Image" },
};

export function humanize(key: string): string {
  const spaced = key.replace(/([a-z0-9])([A-Z])/g, "$1 $2").replace(/[_-]+/g, " ");
  return spaced.charAt(0).toUpperCase() + spaced.slice(1).toLowerCase();
}

export function fieldLabel(key: string): string {
  return FIELD_LABELS[key]?.label ?? humanize(key);
}

export function fieldHint(key: string): string | undefined {
  return FIELD_LABELS[key]?.hint;
}

const OPTION_LABELS: Record<string, string> = {
  paper: "Paper (warm white)",
  cream: "Cream",
  white: "White",
  himalaya: "Himalaya blue",
  deep: "Deep night",
  sindoor: "Sindoor red",
  marigold: "Marigold",
  full: "Full",
  large: "Large",
  medium: "Medium",
  dark: "Dark",
  light: "Light",
  featured: "Featured projects",
  latest: "Latest projects",
  manual: "Choose manually",
  album: "From an album",
  images: "Hand-picked images",
  masonry: "Mosaic",
  grid: "Grid",
  carousel: "Carousel",
  band: "Band over photo",
  story: "Personal story",
  settings: "Impact numbers from Settings (same everywhere)",
  custom: "Numbers just for this section",
  ANNUAL_REPORT: "Annual reports",
  FINANCIAL_STATEMENT: "Financial statements",
  SOCIAL_REPORT: "Social reports",
  OTHER: "Other documents",
  volunteer: "Volunteer",
  membership: "Membership",
  partner: "Partner",
  sm: "Small",
  md: "Medium",
  lg: "Large",
  xl: "Extra large",
};

export function optionLabel(value: string): string {
  return OPTION_LABELS[value] ?? humanize(value);
}

/** One-line summary of a section for the builder list (first meaningful text). */
export function sectionSummary(content: Record<string, unknown>): string {
  for (const key of ["heading", "quote", "kicker", "caption", "lead", "body"]) {
    const v = content[key];
    if (typeof v === "string" && v.trim()) {
      const accent =
        key === "heading" && typeof content.headingAccent === "string" ? ` ${content.headingAccent}` : "";
      return `${v}${accent}`.slice(0, 120);
    }
  }
  const items = content.items ?? content.members ?? content.partners ?? content.images;
  if (Array.isArray(items)) return `${items.length} item${items.length === 1 ? "" : "s"}`;
  return "";
}

import { emptyDoc } from "@/lib/richtext/schema";
import type {
  AlbumFormValues,
  EventFormValues,
  NewsFormValues,
  ProjectFormValues,
  ReportFormValues,
} from "./schemas";

/** Empty form values for "new" editors. Client-safe. */
const seo = { seoTitle: "", seoDescription: "" };

export const emptyNews = (): NewsFormValues => ({
  publishedAt: "",
  coverImageId: null,
  categoryId: null,
  projectId: null,
  authorId: null,
  isFeatured: false,
  tags: [],
  translations: {
    it: { title: "", slug: "", excerpt: "", body: emptyDoc(), ...seo },
    en: { enabled: false, title: "", slug: "", excerpt: "", body: emptyDoc(), ...seo },
  },
});

export const emptyProject = (): ProjectFormValues => {
  const tr = { title: "", slug: "", sector: "", metaLine: "", summary: "", body: emptyDoc(), ...seo };
  return {
    stage: "ACTIVE",
    district: "",
    latitude: "",
    longitude: "",
    startDate: "",
    endDate: "",
    goalAmount: "",
    raisedAmount: "",
    currency: "EUR",
    coverImageId: null,
    isFeatured: false,
    position: 0,
    publishedAt: "",
    translations: { it: tr, en: { ...tr, enabled: false } },
  };
};

export const emptyEvent = (): EventFormValues => {
  const tr = { title: "", slug: "", summary: "", locationName: "", body: emptyDoc(), ...seo };
  return {
    startsAt: "",
    endsAt: "",
    allDay: false,
    timezone: "Europe/Rome",
    address: "",
    mapUrl: "",
    onlineUrl: "",
    registrationUrl: "",
    coverImageId: null,
    isFeatured: false,
    publishedAt: "",
    translations: { it: tr, en: { ...tr, enabled: false } },
  };
};

export const emptyAlbum = (): AlbumFormValues => ({
  coverImageId: null,
  projectId: null,
  takenAt: "",
  position: 0,
  publishedAt: "",
  items: [],
  translations: {
    it: { title: "", slug: "", description: "" },
    en: { enabled: false, title: "", slug: "", description: "" },
  },
});

export const emptyReport = (): ReportFormValues => ({
  type: "ANNUAL_REPORT",
  year: new Date().getFullYear() - 1,
  fileId: null,
  coverImageId: null,
  position: 0,
  publishedAt: "",
  translations: {
    it: { title: "", description: "" },
    en: { enabled: false, title: "", description: "", fileId: null },
  },
});

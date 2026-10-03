/**
 * Stand-in for `next/image` (Vite alias) for the ADMIN bundle: renders the same <img> attributes
 * Next would for unoptimized images (lazy unless `priority`, async decoding, `fill` → absolute cover).
 * The public site renders images in Blade (resources/views/components/site/media-image.blade.php).
 */
import { forwardRef, type ImgHTMLAttributes } from "react";

export type ImageProps = Omit<ImgHTMLAttributes<HTMLImageElement>, "src" | "width" | "height"> & {
  src: string | { src: string; width?: number; height?: number };
  alt: string;
  width?: number | `${number}`;
  height?: number | `${number}`;
  fill?: boolean;
  priority?: boolean;
  quality?: number;
  unoptimized?: boolean;
  placeholder?: string;
  blurDataURL?: string;
};

const Image = forwardRef<HTMLImageElement, ImageProps>(function Image(
  {
    src,
    alt,
    width,
    height,
    fill,
    priority,
    quality: _q,
    unoptimized: _u,
    placeholder: _p,
    blurDataURL: _b,
    style,
    sizes,
    ...rest
  },
  ref,
) {
  const url = typeof src === "string" ? src : src.src;
  return (
    <img
      ref={ref}
      src={url}
      alt={alt}
      width={fill ? undefined : width}
      height={fill ? undefined : height}
      sizes={sizes}
      loading={priority ? "eager" : "lazy"}
      fetchPriority={priority ? "high" : undefined}
      decoding="async"
      style={
        fill
          ? {
              position: "absolute",
              height: "100%",
              width: "100%",
              inset: 0,
              color: "transparent",
              ...style,
            }
          : { color: "transparent", ...style }
      }
      {...rest}
    />
  );
});

export default Image;

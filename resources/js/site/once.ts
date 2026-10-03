/** Per-island "already enhanced" registry, so re-running an init (preview content swaps) never doubles listeners. */
const bound = new Map<string, WeakSet<Element>>();

/** True the first time `el` is seen for `island`; false afterwards. */
export function once(island: string, el: Element): boolean {
  let set = bound.get(island);
  if (!set) bound.set(island, (set = new WeakSet()));
  if (set.has(el)) return false;
  set.add(el);
  return true;
}

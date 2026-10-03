/** Minimal types for idiomorph (the package ships none). */
declare module "idiomorph" {
  export interface IdiomorphConfig {
    morphStyle?: "outerHTML" | "innerHTML";
    ignoreActiveValue?: boolean;
    callbacks?: {
      beforeNodeMorphed?: (oldNode: Node, newNode: Node) => boolean | void;
      beforeAttributeUpdated?: (name: string, node: Element, mutation: "update" | "remove") => boolean | void;
    };
  }
  export const Idiomorph: {
    morph(oldNode: Element, newContent: string | Node, config?: IdiomorphConfig): Node[] | undefined;
  };
}

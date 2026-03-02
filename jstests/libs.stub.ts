/** Test stub for src/libs.ts — avoids top-level await which breaks CommonJS. */
const BgaAnimations = {
  Manager: class {
    animationsActive() {
      return false;
    }
  }
} as any;

export { BgaAnimations };

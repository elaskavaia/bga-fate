import { expect } from "chai";
import sinon from "sinon";
import { LaAnimations } from "../src/LaAnimations";

describe("LaAnimations.shrinkAndFade", () => {
  let la: LaAnimations;
  let clock: sinon.SinonFakeTimers;

  beforeEach(() => {
    document.body.innerHTML = '<div id="oversurface"></div><div id="container"><div id="target">hello</div></div>';
    la = new LaAnimations();
    clock = sinon.useFakeTimers();
  });

  afterEach(() => {
    clock.restore();
  });

  it("should hide the original element during animation", () => {
    la.shrinkAndFade("target");
    expect($("target").style.opacity).to.equal("0");
  });

  it("should create a clone with _shrink suffix on oversurface", () => {
    la.shrinkAndFade("target");
    const clone = $("target_shrink");
    expect(clone).to.not.be.null;
    expect(clone.parentElement.id).to.equal("oversurface");
  });

  it("should set shrink transform and fade on the clone", () => {
    la.shrinkAndFade("target");
    const clone = $("target_shrink");
    expect(clone.style.opacity).to.equal("0");
    expect(clone.style.transform).to.include("scale(0)");
  });

  it("should restore opacity and remove clone after duration", () => {
    la.shrinkAndFade("target", 600);
    clock.tick(600);
    expect($("target").style.opacity).to.not.equal("0");
    expect($("target_shrink")).to.be.null;
  });

  it("should resolve promise after duration", async () => {
    const promise = la.shrinkAndFade("target", 500);
    clock.tick(500);
    await promise;
    // If we get here, the promise resolved after the timeout
  });

  it("should use default duration of 600ms when not specified", async () => {
    const promise = la.shrinkAndFade("target");
    clock.tick(599);
    expect($("target_shrink")).to.not.be.null; // clone still exists before 600ms
    clock.tick(1);
    await promise;
    expect($("target_shrink")).to.be.null; // clone removed after 600ms
  });

  it("should do nothing if element does not exist", () => {
    // Should not throw
    la.shrinkAndFade("nonexistent");
    expect($("nonexistent_shrink")).to.be.null;
  });
});

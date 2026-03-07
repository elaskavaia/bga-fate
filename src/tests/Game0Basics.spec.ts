import { expect } from "chai";
import { getPart, getIntPart, getFirstParts, getParentParts } from "../Game0Basics";

describe("Game0Basics utility functions", () => {
  describe("getPart", () => {
    it("should return the i-th underscore-separated part", () => {
      expect(getPart("hero_1", 0)).to.equal("hero");
      expect(getPart("hero_1", 1)).to.equal("1");
    });

    it("should return empty string for out-of-range index", () => {
      expect(getPart("hero_1", 5)).to.equal("");
    });

    it("should support negative indices", () => {
      expect(getPart("card_hero_1_2", -1)).to.equal("2");
      expect(getPart("card_hero_1_2", -2)).to.equal("1");
    });

    it("should handle multi-part token ids", () => {
      expect(getPart("monster_goblin_1", 0)).to.equal("monster");
      expect(getPart("monster_goblin_1", 1)).to.equal("goblin");
      expect(getPart("monster_goblin_1", 2)).to.equal("1");
    });
  });

  describe("getIntPart", () => {
    it("should return parsed integer from the i-th part", () => {
      expect(getIntPart("hex_9_5", 1)).to.equal(9);
      expect(getIntPart("hex_9_5", 2)).to.equal(5);
    });

    it("should return NaN for non-numeric parts", () => {
      expect(getIntPart("hero_bjorn", 1)).to.be.NaN;
    });
  });

  describe("getFirstParts", () => {
    it("should return the first N parts joined by underscore", () => {
      expect(getFirstParts("card_hero_1_2", 2)).to.equal("card_hero");
      expect(getFirstParts("card_hero_1_2", 3)).to.equal("card_hero_1");
    });

    it("should return only the first part when count is 1", () => {
      expect(getFirstParts("monster_goblin_1", 1)).to.equal("monster");
    });

    it("should return full string when count exceeds parts", () => {
      expect(getFirstParts("hero_1", 5)).to.equal("hero_1");
    });
  });

  describe("getParentParts", () => {
    it("should return all but the last part", () => {
      expect(getParentParts("card_hero_1_2")).to.equal("card_hero_1");
      expect(getParentParts("monster_goblin_1")).to.equal("monster_goblin");
    });

    it("should return empty string for single-part ids", () => {
      expect(getParentParts("hero")).to.equal("");
    });
  });
});

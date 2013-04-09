require("../env");
require("../../d3");
require("../../d3.layout");

var vows = require("vows"),
    assert = require("assert");

var suite = vows.describe("d3.layout.tree");

suite.addBatch({
  "tree": {
    topic: d3.layout.tree,
    "can handle an empty children array": function(tree) {
      assert.deepEqual(tree.nodes({children: []}).map(layout), [
        {depth: 0, x: 0.5, y: 0}
      ]);
      assert.deepEqual(tree.nodes({children: [
        {children: []},
        {children: [{}]},
        {children: [{}]}
      ]}).map(layout), [
        {depth: 0, x: .5,   y: 0},
        {depth: 1, x: .125, y: 0.5},
        {depth: 1, x: .375, y: 0.5},
        {depth: 2, x: .375, y: 1},
        {depth: 1, x: .875, y: 0.5},
        {depth: 2, x: .875, y: 1}
      ]);
    }
  }
});

function layout(node) {
  return {
    depth: node.depth,
    x: node.x,
    y: node.y
  };
}

suite.export(module);

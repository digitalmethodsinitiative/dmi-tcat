require("../env");
require("../../d3");

var assert = require("assert");

module.exports = {
  topic: function() {
    var s = d3.select("body").append("div").selectAll("div")
        .data(["one", "two", "three", "four"])
      .enter().append("div")
        .attr("class", String);

    s.filter(function(d, i) { return i > 0; }).append("span");
    s.filter(function(d, i) { return i > 1; }).append("span");
    s[0][3] = null;

    return s.transition()
        .delay(function(d, i) { return i * 13; })
        .duration(function(d, i) { return i * 21; });
  },

  "selects all matching elements": function(transition) {
    var t = transition.selectAll("span");
    assert.domEqual(t[1][0].node.parentNode, transition[0][1].node);
    assert.domEqual(t[2][0].node.parentNode, transition[0][2].node);
    assert.domEqual(t[2][1].node.parentNode, transition[0][2].node);
  },
  "ignores null elements": function(transition) {
    var t = transition.selectAll("span");
    assert.equal(t.length, 3);
  },
  "propagates delay to the selected elements": function(transition) {
    var t = transition.selectAll("span");
    assert.domEqual(t[1][0].delay, 13);
    assert.domEqual(t[2][0].delay, 26);
    assert.domEqual(t[2][1].delay, 26);
  },
  "propagates duration to the selected elements": function(transition) {
    var t = transition.selectAll("span");
    assert.domEqual(t[1][0].duration, 21);
    assert.domEqual(t[2][0].duration, 42);
    assert.domEqual(t[2][1].duration, 42);
  },
  "returns empty if no match is found": function(transition) {
    var t = transition.selectAll("span");
    assert.isEmpty(t[0]);
  },
  "inherits transition id": function(transition) {
    var id = transition.id,
        t0 = transition.selectAll("span"),
        t1 = transition.selectAll("span");
    assert.equal(t0.id, id);
    assert.equal(t1.id, id);
  },
  "groups are not instances of NodeList": function(transition) {
    var t = transition.selectAll(function() { return this.getElementsByClassName("span"); });
    assert.isFalse(t[0] instanceof window.NodeList);
  }
};

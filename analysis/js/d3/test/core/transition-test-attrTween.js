require("../env");
require("../../d3");

var assert = require("assert");

module.exports = {
  topic: function() {
    var cb = this.callback,
        dd = [],
        ii = [],
        tt = [],
        vv = [];

    var s = d3.select("body").html("").append("div").selectAll("div")
        .data(["red", "green"])
      .enter().append("div")
        .attr("color", function(d) { return d3.rgb(d)+""; });

    var t = s.transition()
        .attrTween("color", tween);

    function tween(d, i, v) {
      dd.push(d);
      ii.push(i);
      vv.push(v);
      if (tt.push(this) >= 2) cb(null, {
        selection: s,
        transition: t,
        data: dd,
        index: ii,
        value: vv,
        context: tt
      });
      return i && d3.interpolateHsl(v, "blue");
    }
  },

  // The order here is a bit brittle: because the transition has zero delay,
  // it's invoking the start event immediately for all nodes, rather than
  // pushing each node onto the timer queue (which would reverse the order of
  // callbacks). The order in which tweens are invoked is undefined, so perhaps
  // we should sort the expected and actual values before comparing.

  "defines the corresponding attr tween": function(result) {
    assert.typeOf(result.transition.tween("attr.color"), "function");
  },
  "invokes the tween function": function(result) {
    assert.deepEqual(result.data, ["red", "green"], "expected data, got {actual}");
    assert.deepEqual(result.index, [0, 1], "expected data, got {actual}");
    assert.deepEqual(result.value, ["#ff0000", "#008000"], "expected value, got {actual}");
    assert.domEqual(result.context[0], result.selection[0][0], "expected this, got {actual}");
    assert.domEqual(result.context[1], result.selection[0][1], "expected this, got {actual}");
  },

  "end": {
    topic: function(result) {
      var cb = this.callback;
      result.transition.each("end", function(d, i) { if (i >= 1) cb(null, result); });
    },
    "uses the returned interpolator": function(result) {
      assert.equal(result.selection[0][1].getAttribute("color"), "#0000ff");
    },
    "does nothing if the interpolator is falsey": function(result) {
      assert.equal(result.selection[0][0].getAttribute("color"), "#ff0000");
    }
  }
};

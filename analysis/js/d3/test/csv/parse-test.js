require("../env");
require("../../d3");
require("../../d3.csv");

var vows = require("vows"),
    assert = require("assert");

var suite = vows.describe("d3.csv.parse");

suite.addBatch({
  "parse": {
    topic: function() {
      return d3.csv.parse;
    },
    "returns an array of objects": function(parse) {
      assert.deepEqual(parse("a,b,c\n1,2,3\n"), [{a: "1", b: "2", c: "3"}]);
    },
    "does not strip whitespace": function(parse) {
      assert.deepEqual(parse("a,b,c\n 1, 2,3\n"), [{a: " 1", b: " 2", c: "3"}]);
    },
    "parses quoted values": function(parse) {
      assert.deepEqual(parse("a,b,c\n\"1\",2,3"), [{a: "1", b: "2", c: "3"}]);
      assert.deepEqual(parse("a,b,c\n\"1\",2,3\n"), [{a: "1", b: "2", c: "3"}]);
    },
    "parses quoted values with quotes": function(parse) {
      assert.deepEqual(parse("a\n\"\"\"hello\"\"\""), [{a: "\"hello\""}]);
    },
    "parses quoted values with newlines": function(parse) {
      assert.deepEqual(parse("a\n\"new\nline\""), [{a: "new\nline"}]);
      assert.deepEqual(parse("a\n\"new\rline\""), [{a: "new\rline"}]);
      assert.deepEqual(parse("a\n\"new\r\nline\""), [{a: "new\r\nline"}]);
    },
    "parses unix newlines": function(parse) {
      assert.deepEqual(parse("a,b,c\n1,2,3\n4,5,\"6\"\n7,8,9"), [
        {a: "1", b: "2", c: "3"},
        {a: "4", b: "5", c: "6"},
        {a: "7", b: "8", c: "9"}
      ]);
    },
    "parses mac newlines": function(parse) {
      assert.deepEqual(parse("a,b,c\r1,2,3\r4,5,\"6\"\r7,8,9"), [
        {a: "1", b: "2", c: "3"},
        {a: "4", b: "5", c: "6"},
        {a: "7", b: "8", c: "9"}
      ]);
    },
    "parses dos newlines": function(parse) {
      assert.deepEqual(parse("a,b,c\r\n1,2,3\r\n4,5,\"6\"\r\n7,8,9"), [
        {a: "1", b: "2", c: "3"},
        {a: "4", b: "5", c: "6"},
        {a: "7", b: "8", c: "9"}
      ]);
    }
  },
  "parseRows": {
    topic: function() {
      return d3.csv.parseRows;
    },
    "returns an array of arrays": function(parse) {
      assert.deepEqual(parse("a,b,c\n"), [["a", "b", "c"]]);
    },
    "parses quoted values": function(parse) {
      assert.deepEqual(parse("\"1\",2,3\n"), [["1", "2", "3"]]);
      assert.deepEqual(parse("\"hello\""), [["hello"]]);
    },
    "parses quoted values with quotes": function(parse) {
      assert.deepEqual(parse("\"\"\"hello\"\"\""), [["\"hello\""]]);
    },
    "parses quoted values with newlines": function(parse) {
      assert.deepEqual(parse("\"new\nline\""), [["new\nline"]]);
      assert.deepEqual(parse("\"new\rline\""), [["new\rline"]]);
      assert.deepEqual(parse("\"new\r\nline\""), [["new\r\nline"]]);
    },
    "parses unix newlines": function(parse) {
      assert.deepEqual(parse("a,b,c\n1,2,3\n4,5,\"6\"\n7,8,9"), [
        ["a", "b", "c"],
        ["1", "2", "3"],
        ["4", "5", "6"],
        ["7", "8", "9"]
      ]);
    },
    "parses mac newlines": function(parse) {
      assert.deepEqual(parse("a,b,c\r1,2,3\r4,5,\"6\"\r7,8,9"), [
        ["a", "b", "c"],
        ["1", "2", "3"],
        ["4", "5", "6"],
        ["7", "8", "9"]
      ]);
    },
    "parses dos newlines": function(parse) {
      assert.deepEqual(parse("a,b,c\r\n1,2,3\r\n4,5,\"6\"\r\n7,8,9"), [
        ["a", "b", "c"],
        ["1", "2", "3"],
        ["4", "5", "6"],
        ["7", "8", "9"]
      ]);
    }
  }
});

suite.export(module);

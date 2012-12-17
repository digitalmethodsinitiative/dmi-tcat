var _barwidth = 20;
                                    	
var _slicecount = 0;
var _barheights = new Object;
for(var _slice in _data) {
    _slicecount++;
    _barheights[_slice] = 0;
    for(var _word in _data[_slice]) {
        _barheights[_slice] += _data[_slice][_word];
    }
}
                                    	
console.log(_barheights);

var c_width = 800;
var c_height = 400;

var _barspacing = (c_width - (_slicecount * _barwidth)) / (_slicecount - 1);
var _leftspacing = 20;
var _bottomspacing = 5;
                                    	
var _linedist = 5;
var _slices = new Object;	
var _lines = new Object;
                                    	
var r = Raphael(50, 50, c_width + 30, c_height);
r.path("M0 " + c_height + "L" + c_width + " " + c_height).attr({
    stroke: "#000"
});
//r.path("M0 0L0 " + c_height).attr({stroke: "#000"});
                                    	
                                    	
function block(_x,_y,_height,_label) {
                                    		
    var _this = this;
                                    		
    this.labelText = _label;
    this.x = _x;
    this.y = _y;
    this.width = _barwidth;
    this.height = _height;
                                    		
    this.draw = function() { 

        this.rect = r.rect(a(this.x), a(this.y), this.width, this.height).attr({
            fill: "#999", 
            stroke: "#fff"
        });
                                    			
        _this = this;
                                    						        
        _this.rect.node.onclick = function() {
            _this.highlight();
        }
    }
                                            
    this.showlabel = function() {
        console.log(this.labelText);
    }
                                            
    this.highlight = function() {
                                            	
        console.log(this.labelText);
                                            	
        for(var _line in _lines) {
            for(var i = 0; i < _lines[_line].length; i++) {
                _lines[_line][i].color("#999");
            }
        }
                                            	
        for(var i = 0; i < _lines[this.labelText].length; i++) {
            _lines[this.labelText][i].color("#000");
        }
    }
                                            
    this.color = function(_col) {
        _this.rect.attr({
            fill:_col
        });
    }
                                            
    this.showLabel = function() {
        this.label = r.text(a(this.x) + _barwidth + _linedist, a(this.y), this.labelText).attr("text-anchor","start");
    }
                                            
    this.hideLabel = function() {
        this.label.remove();
    }
                                            
    this.connectWith = function(_obj) {
                                            
        console.log(this.x + " " + _obj.x);
                                            
        if(this.x < _obj.x) {
            var from_x = this.x + this.width;
            var to_x = _obj.x;
        } else {
            var from_x = this.x - _linedist;
            var to_x = _obj.x + _obj.width + _linedist;
        }
                                            	
        var from_y = this.y + (this.height / 2);
        var to_y = _obj.y + (_obj.height / 2);

        r.path("M" + from_x + " " + from_y + "L" + to_x + " " + to_y).attr({
            stroke: "#999"
        });
    }
}
                                    	
                                    	
var i = 0;
for(var _slice in _data) {
                                    		
    _slices[_slice] = new Object;
                                    		
    var j = 0;
    var _pos = 0;
    for(var _block in _data[_slice]) {
        _slices[_slice][_block] = new block(_leftspacing + _barspacing * i, (c_height - _bottomspacing - _barheights[_slice] + _pos),_data[_slice][_block],_block);
        _slices[_slice][_block].draw();
                                    			
        _pos += _data[_slice][_block];
        j++;
                                    			
        if(typeof(_lines[_block]) == "undefined") {
            _lines[_block] = new Array();
        }
                                    			
        _lines[_block].push(_slices[_slice][_block]);
    }
                                    		
    i++;
}
                                    	
for(var _word in _lines) {
    for(var i = 1; i < _lines[_word].length; i++) {
        _lines[_word][i].connectWith(_lines[_word][i-1]);
    }
}
                                    	
//console.log(_lines)
                                    	
/*
                var test1 = new block(20,120,"test1");
                test1.draw();
                                    	
                var test2 = new block(300,200,"test2");
                test2.draw();
                                    	
                test2.connectWith(test1);
                                    	
                var test3 = new block(500,150,"test3");
                test3.draw();
                                    	
                test3.connectWith(test2);
         */
                                    	
function pos_y(_v) {
    return c_height - _v;
} 
                                    	
function a(_v) {
    return _v + 0.5;
}
                                    	
function changeInterface(_what,_action) {
                                    		
    if(_what == 'labels') {
        for(var _line in _lines) {
            for(var i = 0; i < _lines[_line].length; i++) {
                if(_action == true) {
                    _lines[_line][i].showLabel();
                } else {
                    _lines[_line][i].hideLabel();
                }
            }
        }
    }
}
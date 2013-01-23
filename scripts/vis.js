var _barwidth = 20;

var _leftspacing = 20;
var _bottomspacing = 5;

var _linedist = 2;

var _anispeed = 700;

                                 	
var _slicecount = 0;
var _barheights = new Object;
var _dataarray = new Object;
var _dataSortSize = new Object;
var _highest = 0;
var _lowest = 100000000000000000000000000;
var _highestel = 0;
var _lowestel = 100000000000000000000000000;


var c_width = 1100;
var c_height = 600;

// finding the highest bar and ordering the words in each slice by their value

for(var _slice in _data) {
    _slicecount++;
    _barheights[_slice] = 0;
   	_dataarray[_slice] = new Array;
    for(var _word in _data[_slice]) {
        _barheights[_slice] += _data[_slice][_word];
        var _tmphash = new Array(_word, _data[_slice][_word]);
        //console.log(_tmphash);
        _dataarray[_slice].push(_tmphash);
        _highestel = (_data[_slice][_word] > _highestel ) ? _data[_slice][_word]:_highestel;
    	_lowestel = (_data[_slice][_word] < _lowestel) ? _data[_slice][_word]:_lowestel;
    }
    
    _highest = (_barheights[_slice] > _highest ) ? _barheights[_slice]:_highest;
    _lowest = (_barheights[_slice] < _lowest) ? _barheights[_slice]:_lowest;
    
	_dataarray[_slice].sort(function(a,b) {
		return b[1] - a[1];
	});
	
	_dataSortSize[_slice] = new Object;				// clear old object and write new
    for(var _key in _dataarray[_slice]) {
    	_dataSortSize[_slice][_dataarray[_slice][_key][0]] = _dataarray[_slice][_key][1];
    	//_data[_slice][_key][_dataarray[_key][0]] = _dataarray[_key][1];
    }
}

// calculating the pixelfactor, colorfactor and normalizing data
var _pf = (c_height - 20) / _highest;
var _cf = (_highestel * _pf) / 200;
for(var _slice in _data) {
	_barheights[_slice] = 0;
	for(var _word in _data[_slice]) {
		_data[_slice][_word] = Math.round(_data[_slice][_word] * _pf);
		_dataSortSize[_slice][_word] = Math.round(_dataSortSize[_slice][_word] * _pf);
		_barheights[_slice] += _data[_slice][_word];
	}
}


console.log(_data);

var _barspacing = (c_width - (_slicecount * _barwidth)) / (_slicecount - 1);
                                    	

var _slices = new Object;	
var _lines = new Object;
var _linescon = new Object;
var _connectors = new Object;
var _labels = new Object;
                                    	
var r = Raphael(document.getElementById("visualization"), c_width + 30, c_height);
//r.path("M" + a(_leftspacing - 5) + " " + c_height + "L" + c_width + " " + c_height).attr({ stroke: "#000" });
r.path("M" + a(_leftspacing - 5) + " " + a(c_height) + "L" + a(_leftspacing - 5) + " " + a(0)).attr({ stroke: "#000" });

this.label = r.text(13,(c_height - 8),"0").attr("text-anchor","end");
this.label = r.text(13,5,_highest).attr("text-anchor","end");
//r.path("M0 0L0 " + c_height).attr({stroke: "#000"});



function block(_slice,_x,_y,_height,_label) {
                                    		
    var _this = this;

	this.slice = _slice;
    this.labelText = _label + " (" + _height + ")";
    this.x = _x;
    this.y = _y;
    this.width = _barwidth;
    this.height = _height;
    this.concol = "rgb(" + (Math.round(this.height / _cf)) + "," + (Math.round(200 - this.height / _cf)) + "," + (Math.round(255 - this.height / _cf)) + ")";
    
    console.log("concol:" + this.concol + " " + _height);

    this.draw = function() {

        this.rect = r.rect(a(this.x), a(this.y), this.width, this.height).attr({
            fill: this.concol,
            //"fill-opacity":0.5, 
            stroke: "#fff"
        });
                                    						        
        _this.rect.node.onclick = function() {
            _this.highlight();
        }
    }
    
	this.move = function(_to) {
		console.log(_to);
		this.y = _to;
		this.rect.animate({y:_to},_anispeed, "<");
	}
                                            
    this.highlight = function() {
                                            	
        console.log(this.labelText);
                                            	
        for(var _line in _lines) {
            for(var i = 0; i < _lines[_line].length; i++) {
                _lines[_line][i].color(_lines[_line][i].concol);
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
        this.label = r.text(a(this.x) + _barwidth + _linedist, a(this.y + 5), this.labelText).attr("text-anchor","start");
    }
                                            
    this.hideLabel = function() {
        this.label.remove();
    }
}

function connector(_obj1,_obj2) {
	
	this.obj1 = _obj1;
	this.obj2 = _obj2;

	this.draw = function() {
   
   		this.calcpoly();
	
		this.line = r.path(this.poly).attr({
			stroke: "#fff",
	        fill: "0-" + this.obj2.concol + "-" + this.obj1.concol
	    });
	    
	    this.line.node.style.opacity = 0.3;
	}
	
	this.calcpoly = function() {
		
		console.log(this.obj1.x + " " + this.obj2.x);
                                        
	    if(this.obj1.x < this.obj2.x) {
	        var from_x = a(this.obj1.x + this.obj1.width);
	        var to_x = a(this.obj2.x);
	    } else {
	        var from_x = a(this.obj1.x - _linedist);
	        var to_x = a(this.obj2.x + this.obj2.width + _linedist);
	    }
	                                        	
	    var from_y = this.obj1.y + (this.obj1.height / 2);
	    var to_y = this.obj2.y + (this.obj2.height / 2);
	
		
		// var v_path1 = "M10 10L10 90L90 90L90 10L10 10";
		this.poly = "M" + from_x + " " + (from_y + (this.obj1.height / 8)) +
					"L" + to_x + " " + (to_y + (this.obj2.height / 8)) +
					"L" + to_x + " " + (to_y - (this.obj2.height / 8)) +
					"L" + from_x + " " + (from_y - (this.obj1.height / 8)) + 
					"L"+ from_x + " " + (from_y + (this.obj1.height / 8));
		
		/*
		double line:
		r.path("M" + from_x + " " + (from_y + (this.height / 8)) + "L" + to_x + " " + (to_y + (_obj.height / 8))).attr({
	        stroke: "#999"
	    });
	
	    r.path("M" + from_x + " " + (from_y - (this.height / 8)) + "L" + to_x + " " + (to_y - (_obj.height / 8))).attr({
	        stroke: "#999"
	    });*
	    
	    single line:
	     r.path("M" + from_x + " " + from_y + "L" + to_x + " " + to_y).attr({
	        stroke: "#999"
	    });  
	    */
	}
	
	this.move = function() {
		
		this.calcpoly();
		
		this.line.animate({path:this.poly},_anispeed,"<");	
	}
}
                                    	

// draw visualization and wordlist
                                    	


drawvis(_data);

function drawvis(_data) {
	
	var i = 0;
	var _wordlisthtml = "";
	
	_lines = new Object;
	
	for(var _slice in _data) {
        
		if(typeof(_slices[_slice]) == "undefined") {
			_slices[_slice] = new Object;
		}
	                          		
	    var j = 0;
	    var _pos = 0;
	    
	    if(typeof(_labels[_slice]) == "undefined") {
			_labels[_slice] = r.text(_leftspacing + _barspacing * i,5,_slice).attr("text-anchor","start");
		}	
	    
	    for(var _block in _data[_slice]) {
	    	
	    	if(typeof(_slices[_slice][_block]) == "undefined") {
				_slices[_slice][_block] = new block(_slice,_leftspacing + _barspacing * i, (c_height - _bottomspacing - _barheights[_slice] + _pos),_data[_slice][_block],_block);
				_slices[_slice][_block].draw();
	    	} else {
	    		_slices[_slice][_block].move(c_height - _bottomspacing - _barheights[_slice] + _pos);
	    	}
            
           	
	        _pos += _data[_slice][_block];
	        j++;
	                                    			
	        if(typeof(_lines[_block]) == "undefined") {
	            _lines[_block] = new Array();
	        }
	                                    			
	        _lines[_block].push(_slices[_slice][_block]);
	    }
                                    		
    	i++;
	}
	
	$("#wordlist").html(_wordlisthtml);
	
	console.log(_lines);
	
	for(var _word in _lines) {
		
		if(typeof(_linescon[_word]) == "undefined") {
			_linescon[_word] = new Array;
		}
		
	    for(var i = 1; i < _lines[_word].length; i++) {
	    	if(typeof(_linescon[_word][i - 1]) == "undefined") {
				_linescon[_word][i - 1] = new connector(_lines[_word][i],_lines[_word][i-1]);
				_linescon[_word][i - 1].draw();
			} else {
				_linescon[_word][i - 1].move();
			}
	    }
	}
}
                                    	
                                    	
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
    
    if(_what == "sorting") {
    	 if(_action == true) {
            drawvis(_dataSortSize);
        } else {
			drawvis(_data);
        }
    	
    }
 	
}

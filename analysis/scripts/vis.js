var _barwidth = 20;
var _leftspacing = 40;
var _bottomspacing = 5;
var _linedist = 2;
var _anispeed = 700;
var _colorMetric = "specificity";

                                 	
var _slicecount = 0;
var _barheights = new Object;
var _dataarray = new Object;
var _dataSortSize = new Object;
var _highest = 0;
var _lowest = 100000000000000000000000000;
var _highestel = 0;
var _lowestel = 100000000000000000000000000;
var _labels = false;


var c_width = 1100;
var c_height = 600;


createInterface();

var _pf = 0;	// pixelfactor
var _cf = 0;	// colorfactor

calculateFactors();

var _barspacing = (c_width - (_slicecount * _barwidth)) / (_slicecount - 1);
                                    	

var _slices = new Object;	
var _lines = new Object;
var _linescon = new Object;
var _connectors = new Object;
var _labels = new Object;

var r = null;

createCanvas();
drawvis(_data);
colorcode(_colorMetric);

function createInterface(){
	
	var _ihtml = '<hr />'+
				 'Color coding: <select onchange=\'colorcode(this.options[this.selectedIndex].value);generate_permalink()\' name=\'vis_colorcoding\'>';
	
	
	// finding the highest bar and ordering the words in each slice by their value

	for(var _slice in _data) {
	    _slicecount++;
	    _barheights[_slice] = 0;
	   	_dataarray[_slice] = new Array;
	    for(var _word in _data[_slice]) {
	        _barheights[_slice] += _data[_slice][_word]["cowordFrequency"];
	        var _tmphash = new Array(_word, _data[_slice][_word]);
	        //console.log(_tmphash);
	        _dataarray[_slice].push(_tmphash);
	        _highestel = (_data[_slice][_word]["cowordFrequency"] > _highestel ) ? _data[_slice][_word]["cowordFrequency"]:_highestel;
	    	_lowestel = (_data[_slice][_word]["cowordFrequency"] < _lowestel) ? _data[_slice][_word]["cowordFrequency"]:_lowestel;
	    }
	    
	    _highest = (_barheights[_slice] > _highest ) ? _barheights[_slice]:_highest;
	    _lowest = (_barheights[_slice] < _lowest) ? _barheights[_slice]:_lowest;
	    
		_dataarray[_slice].sort(function(a,b) {
			return b[1]["cowordFrequency"] - a[1]["cowordFrequency"];
		});
		
		_dataSortSize[_slice] = new Object;				// clear old object and write new
	    for(var _key in _dataarray[_slice]) {
	    	
	    	_dataSortSize[_slice][_dataarray[_slice][_key][0]] = new Object;
	    	
	    	for(var _metric in _dataarray[_slice][_key][1]) {
	    		_dataSortSize[_slice][_dataarray[_slice][_key][0]][_metric] = _dataarray[_slice][_key][1][_metric];
	    	}
	    	//_dataSortSize[_slice][_dataarray[_slice][_key][0]]["cowordFrequency"] = _dataarray[_slice][_key][1]["cowordFrequency"];
	    	//_dataSortSize[_slice][_dataarray[_slice][_key][0]]["specificity"] = _dataarray[_slice][_key][1]["specificity"];
	    }
	}
	
	
	var _exit = false;
	dance:
	for (_slice in _data) {
		for (_term in _data[_slice]) {
			for(_metric in _data[_slice][_term]) {
				var _selected = (_metric == _colorMetric) ? "selected":"";
				_ihtml += '<option value="' + _metric + '" ' + _selected + '>' + _metric + '</option>';
				_exit = true;
			}
			if(_exit == true) {break dance;}
		}
	}

	_ihtml += '</select>'+
			  '<input type="checkbox" onchange="changeInterface(\'labels\',this.checked);generate_permalink()" id="vis_labels"/> Show labels '+
			  '<input type="checkbox" onchange="changeInterface(\'sorting\',this.checked);generate_permalink()"  id="vis_sorting"/> Sort by size';
	
	
	$("#vis_interface").html(_ihtml);
	
}


function calculateFactors(_forpf,_forcf) {

	// calculating the pixelfactor, colorfactor and normalizing data
	_pf = (c_height - 20) / _highest;
	_cf = (_highestel * _pf) / 200;
	for(var _slice in _data) {
		_barheights[_slice] = 0;
		for(var _word in _data[_slice]) {
			_data[_slice][_word]["height"] = Math.round(_data[_slice][_word]["cowordFrequency"] * _pf);
			_dataSortSize[_slice][_word]["height"] = Math.round(_dataSortSize[_slice][_word]["cowordFrequency"] * _pf);
			_barheights[_slice] += _data[_slice][_word]["height"];
		}
	}
}


function createCanvas() {
                                    	
	r = Raphael(document.getElementById("visualization"), c_width + 30, c_height);
	//r.path("M" + a(_leftspacing - 5) + " " + c_height + "L" + c_width + " " + c_height).attr({ stroke: "#000" });
	r.path("M" + a(_leftspacing - 5) + " " + a(c_height) + "L" + a(_leftspacing - 5) + " " + a(0)).attr({stroke: "#000"});
	
	
	r.canvas.onclick = function() {
		for(var _line in _lines) {
			for(var i = 0; i < _lines[_line].length; i++) {
				_lines[_line][i].color(_lines[_line][i].concol);
				//console.log(_lines[_line][i].concol);
			}
		}
	}

	
	var _bottomLabel = r.text(30,(c_height - 9),"0").attr("text-anchor","end");
	var _topLabel = r.text(30,20,_highest).attr("text-anchor","end");
	//r.path("M0 0L0 " + c_height).attr({stroke: "#000"});
}



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
            
           	
	        _pos += _data[_slice][_block]["height"];
	        j++;
	                                    			
	        if(typeof(_lines[_block]) == "undefined") {
	            _lines[_block] = new Array();
	        }
	                                    			
	        _lines[_block].push(_slices[_slice][_block]);
	    }
                                    		
    	i++;
	}
	
	$("#wordlist").html(_wordlisthtml);
	
	//console.log(_lines);
	
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



function colorcode(_sel) {
	
	_colorMetric = _sel;
	
	console.log(_sel);
	
	var _tmphighest = 0;
	var _tmplowest = 10000000000000000;
	
	for(var _slice in _data) {
		for(var _word in _data[_slice]) {
			var _tmphighest = (parseInt(_data[_slice][_word][_colorMetric]) > _tmphighest) ? _data[_slice][_word][_colorMetric]:_tmphighest;
			var _tmplowest = (parseInt(_data[_slice][_word][_colorMetric]) < _tmplowest) ? _data[_slice][_word][_colorMetric]:_tmplowest;
		}
	}
    
    //console.log(_tmplowest + " / " + _tmphighest);
        	
    var _cf = _tmphighest / 100;
	
	for(var _line in _lines) {
        for(var i = 0; i < _lines[_line].length; i++) {
        	
        	var _tmpcol = "rgb(" + (Math.round(_lines[_line][i].values[_colorMetric] / _cf * 2)) + "," + (Math.round(200 - (_lines[_line][i].values[_colorMetric] / _cf * 2))) + "," + (Math.round(255 - (_lines[_line][i].values[_colorMetric] / _cf * 2))) + ")";
        	
        	//console.log(_lines[_line][i].values[_colorMetric] + " " + _tmpcol);
        	_lines[_line][i].concol = _tmpcol;
    		_lines[_line][i].color(_tmpcol);
        }
    }
    
    for(var _word in _linescon) {
    	for(var _con in _linescon[_word]) {
    		_linescon[_word][_con].color();
    	}
    }
    
}


function block(_slice,_x,_y,_values,_label) {
                                    		
    var _this = this;

	this.slice = _slice;
	this.labelID = _label;
    this.labelText = _label + " (" + _values["cowordFrequency"] + ")";
    this.values = _values;
    this.x = _x;
    this.y = _y;
    this.width = _barwidth;
    this.height = _values["height"];
	this.concol = "#000";
	this.labelon = false;
    
   // console.log("concol:" + this.concol + " " + this.height);

    this.draw = function() {

        this.rect = r.rect(a(this.x), a(this.y), this.width, this.height).attr({
            fill: this.concol,
            //"fill-opacity":0.5, 
            stroke: "#fff"
        });
                                    						        
        _this.rect.node.onclick = function() {
            window.setTimeout('_lines["' + _this.labelID + '"][0].highlight()',50);
        }
    }
    
	this.move = function(_to) {
		this.y = _to;
		if(this.labelon == true) {this.label.remove();}
		this.rect.animate({y:_to},_anispeed, "<",function() {
			if(_this.labelon == true) {_this.showLabel()}; 	
		});
	}
                                            
    this.highlight = function() {                              	
        for(var i = 0; i < _lines[this.labelID].length; i++) {
            _lines[this.labelID][i].color("#000");
        }
    }
                                            
    this.color = function(_col) {
    	
        _this.rect.attr({
            fill:_col
        });
    }
                                            
    this.showLabel = function() {
    	this.labelon = true;
        this.label = r.text(a(this.x) + _barwidth + _linedist, a(this.y + (this.height / 2)), this.labelText).attr("text-anchor","start");
    }
                                            
    this.hideLabel = function() {
		if(this.label) {
			this.labelon = false;
			this.label.remove();
		}
    }
}

function connector(_obj1,_obj2) {
	
	var _this = this;
	
	this.obj1 = _obj1;
	this.obj2 = _obj2;

	this.draw = function() {
   
   		this.calcpoly();
	
		this.line = r.path(this.poly).attr({
			stroke: "#fff"
	   });
	}
	
	this.color = function() {
		_this.line.attr({
	        fill: "0-" + _this.obj2.concol + "-" + _this.obj1.concol
	    });
	    
		_this.line.node.style.opacity = 0.3;
	}
	
	this.calcpoly = function() {
		
		//console.log(this.obj1.x + " " + this.obj2.x);
                                        
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
function encode_as_img_and_link() {

	 $("svg").attr({ version: '1.1' , xmlns:"http://www.w3.org/2000/svg"});
	 var svg = $("#visualization").html();
	 var b64 = window.btoa(unescape(encodeURIComponent(svg)));
	 
	 // Works in recent Webkit(Chrome)
	 $("#svgdown").html($('<img style="width:25px;height:25px;" src="data:image/svg+xml;base64,\n'+b64+'" alt="file.svg"/>'));
	
	 // Works in Firefox 3.6 and Webit and possibly any browser which supports the data-uri
	 $("#svgdown").html($('<a style="width:25px;height:25px;" href-lang="image/svg+xml" href="data:image/svg+xml;base64,\n'+b64+'" title="file.svg">Download</a>'));
}

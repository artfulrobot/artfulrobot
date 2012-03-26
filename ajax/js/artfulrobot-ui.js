/*
Copyright 2011, Rich Lott

This file is part of Artful Robot Libraries.

Artful Robot Libraries is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by the Free
Software Foundation, either version 3 of the License, or (at your option) any
later version.

Artful Robot Libraries is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
details.

You should have received a copy of the GNU General Public License along with
Artful Robot Libraries.  If not, see <http://www.gnu.org/licenses/>.
*/

var SelectableList = artfulrobot.defineClass( artfulrobot.ARLObject, //{{{
{
/**
	ul.SelectableList li:hover>div.row { background:yellow; }
	ul.SelectableList li.selected div.row { background:red; }
		this.tmp = this.addSubObject( 'SelectableList', [ this.myHTML ]);
		this.tmp.connectSignal( 'selected', this.getCallback('tmp2') );
		this.tmp.setData( [
				{ li : '<div class="row" ><big>Rich Lott</big><br/><span style="font-size:0.8em" >61812 proof of concept?<br />what abou thtis thsn?</span></div>',
					record : { iid: 61812, name: 'rich lott' } 
				},
				{ li : '<div class="row" ><big>Dupelicate Lott</big><br/>1002121 proof of concept?<br />what abou thtis thsn?</div>',
					record : { iid: 1002121, name: 'Duplicate lott' },
				},
				]);
*/
	localInitialise: function( nodeId )  // {{{
	{ 
		console.info('SelectableList.localInitialise');
		// attach to screen area
		this.myHTML = jQuery('#' +nodeId);
		this.records    = false;
		this.selectedI  = -1;
		this.selectedRecord = false;
	}, // }}}
	setData: function( resultsArray ) // {{{
	{
		console.log(this.name+'.setData');
		/* render list inside our html element from data object supplied
		 * which is an array of objects each having two subobjects:
		 * - li (string of HTML) and
		 * - record (another object with whatever is required in it).
		 *   this is what gets returned when a list item is clicked.
		 */
		this.records =[];
		this.myHTML.empty();
		var lis = [];
		var i=0;
		for (i in resultsArray)
		{
			var row=resultsArray[i];
			lis.push( 
					{
						element: 'li',
						innerHTML : row.li,
						id: this.myId + '_li' + i,
						onclick: [this.getCallback('clicked'),{idx:i}]
					});
				this.records.push( row.record );
		};
		
		var ul = artfulrobot.createFragmentFromArray([ { element: 'ul', 'id' : this.myId+'_ul','class' : 'SelectableList', content:lis } ] );

		this.myHTML.append(ul);

		// bind buttons
		this.myHTML.find('button').click( this.getCallback('buttonClicked') );
	}, // }}}
	showSelectedOnly: function( selectedOnly )//{{{
	{
		if (typeof selectedOnly == 'undefined') selectedOnly = 1;
		var ul = jQuery('#'+this.myId+'_ul');
		if ( !ul) return;
		if (selectedOnly) ul.addClass('selectedOnly');
		else ul.removeClass('selectedOnly');
	},//}}}
	getIndexFromRecord: function( value, field ) // {{{
	{
		var l=this.records.length;
		var i=0;
		while (i<l)
		{
			if (this.records[i][field] == value ) return i;
			i++;
		}

		return false;
	}, // }}}
	replaceEntry: function( rec, i ) // {{{
	{
		// replace currently selected entry or entry i
		if (typeof(i) == 'undefined') i=this.selectedI;


		if (i<0) 
		{
			console.error(this.name + '.replaceEntry: no entry selected/given');
			return;
		}

		// rec is { li: 'string of html', record: object }

		// store record
		this.records[i]=rec.record;

		var li = jQuery('#'+this.myId + '_li' + i);
		if (!li)
		{
			console.error("Item " + i + " does not exist.");
			return;
		}
		li.html(rec.li);
		
		// bind buttons
		var handler = this.getCallback('buttonClicked');
		li.find('button').click( handler);

	}, // }}}
	clicked: function( e ) // {{{
	{
		// look out for old code whoopsies
		if ( ! ( e && e.data && typeof(e.data.idx)!='undefined') ) console.error(this.name+'.clicked requires event.data.idx');
		var i=e.data.idx;
		if ( this.selectedI == i ) // want to de-select this item
		{
			jQuery('#'+this.myId + '_li' + this.selectedI).removeClass('selected');
			this.selectedI = -1;
			this.selectedRecord = false;
			this.shout( 'selected', { record: false, evt: e } );
		}
		else // select something
		{
			this.select(i);
			this.shout( 'selected', { record: this.selectedRecord, evt: e, index:i } );
		}
	}, // }}}
	select: function(i) // {{{
	{
		// already selected?
		if ( this.selectedI == i ) return;

		// other thing selected? deselect it if so.
		if ( this.selectedI > -1 )
			jQuery('#'+this.myId + '_li' + this.selectedI).removeClass('selected');

		this.selectedI = i;
		jQuery('#'+this.myId + '_li' + i).addClass('selected');
		this.selectedRecord = this.records[i];
	}, // }}}
	buttonClicked: function( e ) // {{{
	{
		// A 2nd click event would happen on the container node
		// that is observed by this.select. We don't want this
		e.stopPropagation();

		// discover index of thing that was clicked
		var btn = e.target;
		var li = btn;
		while (li.tagName != 'LI') li = li.parentNode;
		i = li.id.replace(/^.+_li/,'');
		this.select(i);

		this.shout( 'button_click', { buttonValue:btn.value, button:btn, record: this.records[i], evt: e, index:i } );
	}, // }}}
	cleanup: function() { this.myHTML.empty();}
}); // }}}
// ARLSelectableTable -- for working with a table that the user can select rows from {{{
var ARLSelectableTable = artfulrobot.defineClass( artfulrobot.ARLObject,
{
/** ARLSelectableTable documentation
 *  Quick way to make a table act like a select input.
 *  table receives 'SelectableTable' class name, and 'filterOff' class name
 *  rows receive 'selected' class name when selected.
 *  only one row is selectable at a time.
 *  rows do not respond to clicks if unselectable className exists on tr element.
 *
 *  Note: this is a replacement for the generic SelectableTable object. 
 *  
 *  shouts the following signals: 
 *      rowSelected		with the following object:
						tableId : (string Id, or node or jQuery node)
						event: 
						trNode: 
						rowData: array 
						rowIndex:
						colIndex:
						thNode:
 */ 
	localInitialise: function(tableId, options) // {{{
	{
		this.setSessionDefaults( {filters: []} );

		this.filterRegexps  = [];
		this.filterableCols = [];
		this.nodesArray     = []; // nodesArray[ rowIndex ][ colIndex ] = td 
		this.headerRowNodes = []; 
		this.table          = (artfulrobot.typeOf( tableId )=='string') ? jQuery('#'+tableId) : jQuery(tableId);
		this.class_selected = 'selected';
		this.lastSelected   = false;
		this.isUnfiltered   = true;
		this.thaw();
		// get all rows
		var colIndex = 0;
		var rows = this.table.find('tr');
		// deal with headers
		this.headerRowNodes=rows.first().find('th');
		// initialise filters to zls
		var _fltrs=this._SESSION.filters;
		this.headerRowNodes.each(function(i){ if ( ! _fltrs[i] ) _fltrs[i]= ''; });

		// bind to this object's clicked method
		var rowIndex =0;
		var colIndex = 0;
		var me=this;
		var _nodesArray=this.nodesArray;
		var _cb=this.getCallback('clicked');
		rows.slice(1).each(
			function (rowIndex)
			{
				_nodesArray[ rowIndex ] = [];
				jQuery(this).find('td').each(
					function( colIndex ) {
						var td=jQuery(this);
						_nodesArray[ rowIndex ][ colIndex ] = td;
						td.click( {rowIndex:rowIndex,colIndex:colIndex}, _cb);
						});
			} );

		options = options || {};

		if ( artfulrobot.typeOf(options.filterable) == 'array' ) {
			for(i in options.filterable) this.filterable( options.filterable[i] );
		}
		else if ( artfulrobot.typeOf(options.filterable) == 'number' )
			this.filterable( options.filterable );

		this.filtersChanged();
	}, // }}}
	clicked: function(evt) // xxx {{{
	{
		if (this.frozen) { 
		//	console.warn('clicked frozen table'); 
			return;}
		var rowNode=jQuery(this.origContext).closest('tr');
		var rowIndex=evt.data.rowIndex;
		var colIndex=evt.data.colIndex;

		// check that this row does not have the unselectable class, if so pretend we weren't clicked!
		if ( rowNode.hasClass('unselectable')) return;

		// create an array of row texts
		row = jQuery.map( rowNode.find('td'), function(node) { return jQuery(node).text(); } );

		/* Because this is an event handler, the first argument is an event object.  */
		if ( this.lastSelected!==false && this.lastSelected.rowNode.is(rowNode) ) // deselect
		{
			this.lastSelected.rowNode.removeClass(this.class_selected);
			this.lastSelected = false;
			// send false instead of rowNode, to show nothing selected
			this.shout('rowSelected', 
					{
						tableId: this.table.id,
						event: evt,
						trNode: false,
						rowData: false,
						rowIndex: false,
						colIndex: false,
						thNode: false,
					});
		}
		else
		{
			this.lastSelected && this.lastSelected.rowNode.removeClass(this.class_selected);
			this.lastSelected = 
				{
					rowNode: rowNode,
					cellNode: this.origContext,
					rowIndex:rowIndex,
					colIndex:colIndex
				};
			rowNode.addClass(this.class_selected);
			 // call with the row as "this", passing on the event and the array of row values.
			this.shout('rowSelected', 
					{
						tableId: this.table.id,
						event: evt,
						trNode: rowNode, 
						rowData: row,
						rowIndex:rowIndex,
						colIndex:colIndex,
						thNode:this.headerRowNodes[colIndex]
					});
		}
	}, // }}}
	filter: function(colIndex, re, leaveSelectedAlone ) // {{{
	{
		// This function deals with actually show/hiding rows 
		var l=this.nodesArray.length;
		var nodesArray = this.nodesArray; // reference will save time if loop is big.
		for (var i=0;i<l;i++)
		{
			if ( re.test( nodesArray[i][colIndex].textContent ) )
				nodesArray[i][0].parentNode.show();
			else
				nodesArray[i][0].parentNode.hide();
		}
	}, // }}}
	filtersChanged: function () // {{{
	{
		/** filtersChanged is called when this._SESSION.filters has been updated
		 *  it:
		 *	1. compiles new regexps
		 *	2. updates the filter icon on the apropriate column heads. 
		 *	3. calls applyFilters()
		 */ 
		// clear regexps:
		this.filterRegexps = [];
		for (idx in this.filterableCols)
		{
			colIndex=this.filterableCols[idx];
			var f=this._SESSION.filters[colIndex];
			if (f)
			{
				this.filterRegexps[colIndex] = new RegExp(f,'i');
				this.headerRowNodes.eq(colIndex).children().first()
					.removeClass('filterOff')
					.addClass('filterOn');
			}
			else 
			{
				this.filterRegexps[colIndex] = false;
				this.headerRowNodes.eq(colIndex).children().first()
					.removeClass('filterOn')
					.addClass('filterOff');
			}
		}

		this.applyFilters();
	}, // }}}
	applyFilters: function() // {{{
	{
		var anyFilters = 0;

		// first check needed {{{
		if ( this._SESSION.filters.join('') == '')
		{
			//console.log('No filters, showing all table rows...');
			if (! this.isUnfiltered) this.table.find('tr').show();
			return;
		} // }}}

		// reduce filterableCols by whether there's a filter set for them...
		var checks = [];
		var _re=this.filterRegexps;
		checks = jQuery.map( this.filterableCols, function(colIndex) { 
			return   _re[colIndex] ? colIndex : null; });

		// go through the rows of the table one-by-one
		for (rowIndex in this.nodesArray)
		{
			var show=true;
			for( ci in checks )
			{
				var colIndex=checks[ci];
				if ( ! _re[colIndex].test( this.nodesArray[rowIndex][colIndex].text() ) ) 
				{
					show=false;
				}
			}

			if (show) this.nodesArray[rowIndex][0].closest('tr').show();
			else
			{
				this.nodesArray[rowIndex][0].closest('tr').hide();
				anyFilters++;
			}
		}
		if (anyFilters) { this.table.removeClass('filterOff').addClass('filterOn');this.isUnfiltered=false; }
		else 			{ this.table.removeClass('filterOn').addClass('filterOff');this.isUnfiltered=true; }

	}, // }}}
	filterable: function(colIndex)	// {{{
	{
		for (i in this.filterableCols) {
		   	if (this.filterableCols[i]==colIndex) return;
		}
		this.filterableCols.push(colIndex);
		
		var th = this.headerRowNodes.eq(colIndex);
		// create filter div immediately inside th
		var origContents = th.contents().detach();
		th.html('<div class="filterOff"></div>').find('div').click(
						this.getCallback('autoFilter',colIndex) ).append(origContents);

		// assume this is not called after a user has had a chance to filter the table...
		this.table.addClass('filterOff');
	}, // }}}
	autoFilter: function(colIndex) // {{{
	{
		var f = prompt('Filter ' + this.headerRowNodes[colIndex].firstChild.textContent, 
				this._SESSION.filters[colIndex] && this._SESSION.filters[colIndex] || '' );
		this._SESSION.filters[colIndex] = f?f:false;

		this.filtersChanged();

		// remember the filter settings
		this.saveSession();
	}, // }}}
	setFilter: function(colIndex, regex ) // {{{
	{
		// public function: set up filter if not already filterable
		if (! this.filterableCols[colIndex]) this.filterable(colIndex);
		this._SESSION.filters[colIndex] = regex?regex:false;
		this.filtersChanged();
		// remember the filter settings
		this.saveSession();
	}, // }}}
	freeze: function() // {{{
	{
		this.frozen = true;
		this.table.removeClass('SelectableTable');
	}, //}}}
	thaw: function() // {{{
	{
		this.frozen = false;
		this.table.addClass('SelectableTable');
	}, //}}}
	reset: function() // {{{
	{
		// unselect any row
		this.table.find('tr').removeClass('selected');
		this.lastSelected = false;
	}, //}}}
	selectRow: function(needle, colIndex) // {{{
	{
		var i=0;
		var m=this.nodesArray.length;
		var found = 0;
		for (i=0;i<m;i++) { if ( needle == this.nodesArray[i][colIndex].textContent ) { found=i;break;} }
		this.clicked( { target: this.nodesArray[found][colIndex] }, found, colIndex );
	}, // }}}
}); // }}}

// ARLEditableTable -- for working with a table that the user can edit rows in {{{
var ARLEditableTable = artfulrobot.defineClass( artfulrobot.ARLObject,
{
/**
 *  fieldDefs = {
 *  	fieldName1 : { 
 *  		head: html
 *  		edit: fn() | text | textarea | undef,
 *  		view: fn() | undef,
 *  		},
 *  	fieldName2 ... }
 *
 *  shouts the following signals: 
 *      rowSelected		with the following object:
						tableId :
						event: 
						trNode: 
						rowData: array 
						rowIndex:
						colIndex:
						thNode:
 */ 
	localInitialise: function( nodeId, defs, data, options) // {{{
	{
		this.myHTML = jQuery('#' +nodeId);
		this.defs = defs;
		this.data = data;
		options = options || {};
		this.options = {
			tableId : options.tableId || this.myId+'_t'
			};
		this.render();
	}, // }}}
	render :function()//{{{
	{
		var rows = [];
		var dataLength = this.data.length;
		var thr = jQuery.map(this.defs, function(obj, key) {
				return { element: 'th', content:obj.head || key  } } );
		
		// for each record we loop through the columns in defs
		// and present the view for each bit of data.
		for (i in this.data)
		{
			var data = this.data[i]; // reference for closure:

			var row = jQuery.map( this.defs, function(def,field) {
				return { element: 'td', content: 
					(def.view 
					 ? def.view(data[field], field, data )
					 : data[field] )
					};
				});
			if (row) rows.push( { element: 'tr', content: row } );
		}

		this.myHTML.empty().append( artfulrobot.createFragmentFromArray([
			{ element: 'table', id: this.options.tableId, content: [
				{ element: 'thead', content: 
					{ element: 'tr', content: thr } },
				{ element: 'tbody', content: rows }
				]},
			{ element: 'button', content: 'Add', onclick:this.getCallback('addRow') }
			]));
	},//}}}
	addRow:function()//{{{
	{
	},//}}}
	the:'end'
}); // }}}


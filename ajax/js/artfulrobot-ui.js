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

var SelectableList = artfulrobot.Class.create( artfulrobot.ARLObject, //{{{
{
/**
	ul.SelectableList li:hover>div.row { background:yellow; }
	ul.SelectableList li.selected div.row { background:red; }
		this.tmp = this.addSubObject( 'SelectableList', [ this.myHTML ]);
		this.tmp.connectSignal( 'selected', this.tmp2.bind(this) );
		this.tmp.setData( [
				{ li : '<div class="row" ><big>Rich Lott</big><br/><span style="font-size:0.8em" >61812 proof of concept?<br />what abou thtis thsn?</span></div>',
					record : { iid: 61812, name: 'rich lott' } 
				},
				{ li : '<div class="row" ><big>Dupelicate Lott</big><br/>1002121 proof of concept?<br />what abou thtis thsn?</div>',
					record : { iid: 1002121, name: 'Duplicate lott' },
				},
				]);
*/
	localInitialize: function( html )  // {{{
	{ 
		console.info('SelectableList.localInitialize');
		// attach to screen area
		this.myHTML     = html
		this.records    = false;
		this.selectedI  = -1;
		this.selectedRecord = false;
	}, // }}}
	setData: function( resultsArray ) // {{{
	{
		/* render list inside our html element from data object supplied
		 * which is an array of objects each having two subobjects:
		 * - li (string of HTML) and
		 * - record (another object with whatever is required in it).
		 *   this is what gets returned when a list item is clicked.
		 */
		this.records =[];
		$(this.myHTML).innerHTML = '';
		var lis = [];
		var i=0;
		var me = this;
		$A(resultsArray).each( function( row )
			{
				lis.push( 
					{
						element: 'li', 
						innerHTML : row.li,
						id: me.myId + '_li' + i ,
						onclick: me.clicked.bindAsEventListener(me, i)
					});
				me.records.push( row.record );
				i++;
			});
		
		var ul = createFragmentFromArray([ { element: 'ul', 'id' : this.myId+'_ul','class' : 'SelectableList', content:lis } ] );

		$(this.myHTML).appendChild(ul);

		// bind buttons
		var handler = this.buttonClicked.bind(this);
		$(this.myHTML).select('button').each( function( btn ) { 
				Event.observe( btn, 'click', handler);
				});
	}, // }}}
	showSelectedOnly: function( selectedOnly )//{{{
	{
		if (typeof selectedOnly == 'undefined') selectedOnly = 1;
		var ul = $(this.myId+'_ul');
		if ( !ul) return;
		if (selectedOnly) ul.addClassName('selectedOnly');
		else ul.removeClassName('selectedOnly');
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
		if (typeOfThing(i) == 'undefined') i=this.selectedI;


		if (i<0) 
		{
			console.error(this.name + '.replaceEntry: no entry selected/given');
			return;
		}

		// rec is { li: 'string of html', record: object }

		// store record
		this.records[i]=rec.record;

		var li = $(this.myId + '_li' + i);
		if (!li)
		{
			console.error("Item " + i + " does not exist.");
			return;
		}
		li.innerHTML = rec.li;
		
		// bind buttons
		var handler = this.buttonClicked.bind(this);
		li.select('button').each( function( btn ) { 
				Event.observe( btn, 'click', handler);
				});

	}, // }}}
	clicked: function( e, i ) // {{{
	{
		if ( this.selectedI == i ) // want to de-select this item
		{
			$(this.myId + '_li' + this.selectedI).removeClassName('selected');
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
			$(this.myId + '_li' + this.selectedI).removeClassName('selected');

		this.selectedI = i;
		$(this.myId + '_li' + i).addClassName('selected');
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
		console.warn("buttonClicked: ", li.id, i, this.selectedI);
		this.select(i);

		this.shout( 'button_click', { buttonValue:btn.value, button:btn, record: this.records[i], evt: e, index:i } );
	}, // }}}
	cleanup: function()  // {{{
	{ 
		$(this.myHTML).innerHTML = ''; 
	} // }}}
}); // }}}


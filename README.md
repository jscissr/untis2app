untis2app
=========

Generate timetable webapps for smartphones from the HTML export of [Untis](http://www.grupet.at/en/produkte/untis/uebersicht_untis.php).

An example is http://kantiplan.appspot.com/, the source files for this are on http://kantiolten.ch/schueler/aktuell/.

Usage
=====

This tool is written in PHP, so you need PHP to use it, and probably an http server like apache. You can install [XAMPP](https://www.apachefriends.org/), this package contains php and apache. Put the untis2app folder into your htdocs folder.
There is no GUI, so you need to configure some things directly in the code. Good code editors are for example [Notepad++](http://notepad-plus-plus.org/) (sadly only for Windows) or [Brackets](http://brackets.io/).

By default, it will write the output files to the folder `output`. If you want to use [appspot](https://appengine.google.com/) like I did in the example, change the marked places in the code.

1._create_plans.json.php
------------------------
First, you need your timetables from the HTML export functionality of the Untis software. You can export it yourself, or use existing data from an http server.
Open 1._create_plans.json.php with a code editor, and fill `$base`.
Next, you need the identifier for the time period. For this I don't have an easy solution yet, you need to look at the source code of `$base`/frames/navbar.htm . Scroll down to about the middle of the file, and look for this part:

```html
     <!-- week selection -->
     <td align="left" class="tabelle">
      <span class="selection">
       <nobr>
        Periode<br>
        <span class="absatz">
         &nbsp;<br>
        </span>
        <select name="week" class="selectbox" style='width:114' onChange="doDisplayTimetable(NavBar, topDir);">
<option value="P1">11.8. - 15.2.</option>
        </select>
       </nobr>
      </span>
     </td>
```

In this case, you would copy `P1` to 1._create_plans.json.php.
Now, run the php file using you browser (something like http://localhost/untis2app/1._create_plans.json.php). You should see JSON data like this:

```json
[
    {
        "name": "1aP",
        "source": "path\/to\/timetables\/c\/P1\/c00001.htm",
        "file": "1ap"
    },
    {
        "name": "3aL",
        "source": "path\/to\/timetables\/c\/P1\/c00002.htm",
        "file": "3al",
        "childs": [
            {
                "name": "Aletti Alice",
                "source": "path\/to\/timetables\/s\/P1\/s00001.htm",
                "file": "aletti-alice"
            },
            {
                "name": "Barret Bob",
                "source": "path\/to\/timetables\/s\/P1\/s00002.htm",
                "file": "barret-bob"
            }
        ]
    }
]
```

2._get_all_raw_html_files.php
-----------------------------

This part is optional, but recommended if the source files are on an http server. It will get all the source files, store them to raw/ and adjust the JSON file.
You have to comment out the line `die('disabled');` with `//`. This is to prevent accidental exection.

3.0_create_timetables_preview.php
---------------------------------

In this file you have to adjust the times and the A/B weeks. Open it in the browser to see a preview of the app.

3.1_create_timetables.php
-------------------------

When you're happy with the preview, you can export all tables to seperate html files. If you'd like to put the result on appspot, change the marked strings in the file.

4._create_index.php
-------------------

This final step produces the index.htm file, which contains a list of links to the individual plans. You can adjust this as you want, please note that there is an unminified and a minified part.


Now you can upload the `output` folder to your webspace or the `appspot` folder to appengine.


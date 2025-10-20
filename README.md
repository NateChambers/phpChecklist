# A fairly simple, no-registration, checklist creater in an easy to use single php file.

**FEATURES**
- Light and Dark theme (dark by default)
- Automatically creates database
- Create checklist with randomized password for editing
- Progress status bar
- Edit checklist (requires checklist password)
- Lock checklist (requires checklist password)
- Reset checklist (requires checklist password)
- Delete checklist (requires checklist password)
- Clone checklist
  

**SUGGESTIONS TO USERS**
- It may be useful to create a cronjob (or windows scheduled task) to automatically backup the database on a regular basis.


**TODO**
- More unique IDs, maybe uid instead of id?
- Maybe an options variable for default theme, if dark theme not prefered it can easily be changed.
- Edit mode to modify title and/or checklist


**INSTRUCTIONS**

Simply copy/paste (drag and drop) in to your server. Rename as you like. The rest is automatic!
Note: If the database isn't being created, you may need to change chmod 0775 index.php


# DISCLAIMER: Because I hadn't used php since ~2009, I used deepseek to modify/adjust/improve some parts of the script.

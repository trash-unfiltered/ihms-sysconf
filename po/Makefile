# Sysconf - Interactive configuration system for PHP applications
# Copyright (C) 2012 by iHMS Team
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
#
# copyright   2012 by iHMS Team
# author      Laurent Declercq <l.declercq@nuxwin.com>
# version     0.0.1
# link        https://github.com/i-HMS/sysconf Sysconf Home Site
# license     http://www.gnu.org/licenses/gpl-2.0.html GPL v2

# List here all source files with translatable strings.
POTFILES=$(sort $(shell find ../library -type f -name \*.php)) ../ihms-* ../sysconf-*

POFILES=$(wildcard *.po)
MOFILES=$(POFILES:.po=.mo)

all: sysconf.pot $(MOFILES)

install: all
	for file in $(MOFILES); do \
		lang=`echo $$file | sed 's/\.mo//'`; \
		install -d $(DESTDIR)$(prefix)/share/ihms/locale/$$lang/LC_MESSAGES/; \
		install -m 0644 $$file $(DESTDIR)$(prefix)/share/ihms/locale/$$lang/LC_MESSAGES/sysconf.mo; \
	done

sysconf.pot: $(POTFILES)
	@echo "Rebuilding the pot file"
	xgettext $(POTFILES) -o sysconf.pot -Lphp

clean:
	rm -f $(MOFILES) messages messages.mo

%.mo: %.po
	msgfmt -o $@ $<

%.po: sysconf.pot
	@echo -n "Merging sysconf.pot and $@"
	@msgmerge $@ sysconf.pot -o $@.new
	# Typically all that changes was a date or line number. I'd prefer not to
	# commit such changes, so detect and ignore them.
	@if [ "`diff $@ $@.new | grep '[<>]' | grep -v '[<>] #:' | wc -l`" -ne 2 ]; then \
		mv -f $@.new $@; \
	else \
		rm -f $@.new; \
	fi
	@msgfmt --statistics $@

check:
	@for file in $(POFILES); do \
		lang=`echo $$file | sed 's/\.po//'`; \
		printf "$$lang: "; \
		msgfmt -o /dev/null -c -v --statistics $$lang.po;\
	done

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

MANPAGES = $(sort $(shell find man -type f ! -name \*.gz))
MANPAGES_GZ = $(MANPAGES:=.gz)

.PHONY: all install uninstall distclean clean

all: $(MANPAGES_GZ)

install: $(MANPAGES_GZ)
	install -d $(DESTDIR)$(mandir)
	# Install man pages
	for num in 1 5 8; do \
		install -d $(DESTDIR)$(mandir)/man$$num; \
		find $^ -name "*.$$num.gz" | xargs -i install {} $(DESTDIR)$(mandir)/man$$num; \
	done

%.gz: %
	cat $< | sed -e "s|@sysconfdir@|$(sysconfdir)|g" -e "s|@localstatedir@|$(localstatedir)|g" | gzip -9 - > $@

uninstall:
	find $(mandir) -type f -name ihms-preconfigure.8.gz -or -name ihms-reconfigure.8.gz -or \
		-name sysconf\*.gz | xargs -i rm {}

distclean: clean
clean:
	$(RM) $(MANPAGES_GZ)

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

MAN = $(sort $(shell find man -type f))
MANGS = $(MAN:=.gz)

all: $(MANGS)

install: $(MANGS)
	install -d $(DESTDIR)$(prefix)/share
	# Create man directories
	find man -type d | xargs -i install -d $(DESTDIR)$(prefix)/share/{}
	# install man pages
	find man -type f -name \*.gz | xargs -i install -m 0644 {} $(DESTDIR)$(prefix)/share/{}

%.gz: %
	gzip -9 < $< > $@

distclean: clean
clean:
	$(RM) man/*/ihms*.gz man/*/sysconf*.gz

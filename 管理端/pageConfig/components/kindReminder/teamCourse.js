require("../../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageConfig/components/kindReminder/teamCourse"],
    {
      "093f": function (n, e, o) {},
      "35d5": function (n, e, o) {
        "use strict";
        o.r(e);
        var t = o("7613"),
          r = o("7f6d");
        for (var u in r)
          ["default"].indexOf(u) < 0 &&
            (function (n) {
              o.d(e, n, function () {
                return r[n];
              });
            })(u);
        o("4c64");
        var i = o("828b"),
          c = Object(i.a)(
            r.default,
            t.b,
            t.c,
            !1,
            null,
            null,
            null,
            !1,
            t.a,
            void 0,
          );
        e.default = c.exports;
      },
      "45c6": function (n, e, o) {
        "use strict";
        Object.defineProperty(e, "__esModule", { value: !0 }),
          (e.default = void 0),
          (e.default = {
            props: { kindReminder: {} },
            data: function () {
              return { loading: !0, info: {} };
            },
          });
      },
      "4c64": function (n, e, o) {
        "use strict";
        var t = o("093f");
        o.n(t).a;
      },
      7613: function (n, e, o) {
        "use strict";
        o.d(e, "b", function () {
          return r;
        }),
          o.d(e, "c", function () {
            return u;
          }),
          o.d(e, "a", function () {
            return t;
          });
        var t = {
            uParse: function () {
              return Promise.all([
                o.e("common/vendor"),
                o.e("uview-ui/components/u-parse/u-parse"),
              ]).then(o.bind(null, "eb32"));
            },
          },
          r = function () {
            this.$createElement;
            this._self._c;
          },
          u = [];
      },
      "7f6d": function (n, e, o) {
        "use strict";
        o.r(e);
        var t = o("45c6"),
          r = o.n(t);
        for (var u in t)
          ["default"].indexOf(u) < 0 &&
            (function (n) {
              o.d(e, n, function () {
                return t[n];
              });
            })(u);
        e.default = r.a;
      },
    },
  ]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pageConfig/components/kindReminder/teamCourse-create-component",
    {
      "pageConfig/components/kindReminder/teamCourse-create-component":
        function (n, e, o) {
          o("df3c").createComponent(o("35d5"));
        },
    },
    [["pageConfig/components/kindReminder/teamCourse-create-component"]],
  ]);

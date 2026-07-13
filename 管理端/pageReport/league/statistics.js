(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageReport/league/statistics"],
  {
    "37dc": function (t, a, e) {
      "use strict";
      (function (t, a) {
        var n = e("47a9");
        e("86d2"), n(e("3240"));
        var i = n(e("c5c6"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), a(i.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "4c1b": function (t, a, e) {
      "use strict";
      (function (t) {
        var n = e("47a9");
        Object.defineProperty(a, "__esModule", { value: !0 }),
          (a.default = void 0);
        var i = n(e("af34")),
          r = e("4689"),
          o = {
            data: function () {
              return {
                list: [],
                coachScreeningNum: "",
                parma: { year: "", month: "" },
                dateList: [],
                scrollView: "",
              };
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var a = t.getMenuButtonBoundingClientRect();
                return (
                  a.height +
                  2 * (a.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            methods: {
              dateClick: function (t) {
                this.dateList.forEach(function (t) {
                  t.active = !1;
                }),
                  (t.active = !0),
                  (this.parma.year = t.year),
                  (this.parma.month = t.month),
                  this.getList(this.parma);
              },
              getList: function (t) {
                var a = this;
                (0, r.getTeamArrangeCount)(t).then(function (t) {
                  a.list = t.list;
                });
              },
              createMonth: function (t) {
                for (var a = [], e = 1; e < 13; e++) {
                  var n =
                    this.todayStr().year == t && this.todayStr().month == e;
                  a.push({
                    year: "".concat(t),
                    month: e,
                    str: "date_".concat(t).concat(e),
                    active: n,
                    isToday: n,
                  });
                }
                return a;
              },
              createYear: function (t, a) {
                for (var e = [], n = t; n < a; n++)
                  e = [].concat(
                    (0, i.default)(e),
                    (0, i.default)(this.createMonth(n)),
                  );
                return e;
              },
              todayStr: function () {
                var t = new Date();
                return { year: t.getFullYear(), month: t.getMonth() + 1 };
              },
              headleDelete: function (a) {
                this.$store.commit("LEAGUE_DELETE", { leagueDelete: a }),
                  this.$store.commit("STR_MONTH", {
                    strmonth: this.parma.month,
                  }),
                  this.$store.commit("YEAR", { year: this.parma.year }),
                  t.navigateTo({ url: "/pageReport/league/details" });
              },
              initDate: function (t, a) {
                (this.parma.year = t), (this.parma.month = a);
              },
              loadTeamData: function (t) {
                this.parma.year = t.slice(0, 4);
                var a = t.slice(5, 7);
                (this.parma.month = a >= 10 ? a : a.slice(1, 2)),
                  this.getList(this.parma);
              },
              datechange: function (t) {
                this.loadTeamData(t.fullDate);
              },
              daysChange: function (t) {
                this.isGoBackToday = t.isGoBackToday;
              },
            },
            onLoad: function () {
              this.dateList = this.createYear(2022, 2030);
              var t = "date_"
                  .concat(this.todayStr().year)
                  .concat(this.todayStr().month),
                a = this.dateList.findIndex(function (a) {
                  return a.str == t;
                });
              a > 1 && (a -= 1),
                (this.scrollView = this.dateList[a].str),
                (this.parma.year = this.todayStr().year),
                (this.parma.month = this.todayStr().month),
                this.getList(this.parma);
            },
          };
        a.default = o;
      }).call(this, e("df3c").default);
    },
    5109: function (t, a, e) {
      "use strict";
      var n = e("91f6");
      e.n(n).a;
    },
    "601a": function (t, a, e) {
      "use strict";
      e.d(a, "b", function () {
        return i;
      }),
        e.d(a, "c", function () {
          return r;
        }),
        e.d(a, "a", function () {
          return n;
        });
      var n = {
          uDivider: function () {
            return e
              .e("uview-ui/components/u-divider/u-divider")
              .then(e.bind(null, "5ef0a"));
          },
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
        },
        i = function () {
          var t = this,
            a = (t.$createElement, t._self._c, t.list.length),
            e =
              a > 0
                ? t.__map(t.list, function (a, e) {
                    return {
                      $orig: t.__get_orig(a),
                      m0: t.imgsrc(a.staffFace),
                    };
                  })
                : null,
            n = a > 0 ? null : t.imgsrc("/static/imgs/nodata.png");
          t.$mp.data = Object.assign({}, { $root: { g0: a, l0: e, m1: n } });
        },
        r = [];
    },
    "91f6": function (t, a, e) {},
    a7a0: function (t, a, e) {
      "use strict";
      e.r(a);
      var n = e("4c1b"),
        i = e.n(n);
      for (var r in n)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            e.d(a, t, function () {
              return n[t];
            });
          })(r);
      a.default = i.a;
    },
    c5c6: function (t, a, e) {
      "use strict";
      e.r(a);
      var n = e("601a"),
        i = e("a7a0");
      for (var r in i)
        ["default"].indexOf(r) < 0 &&
          (function (t) {
            e.d(a, t, function () {
              return i[t];
            });
          })(r);
      e("5109");
      var o = e("828b"),
        s = Object(o.a)(
          i.default,
          n.b,
          n.c,
          !1,
          null,
          "16a03ffc",
          null,
          !1,
          n.a,
          void 0,
        );
      a.default = s.exports;
    },
  },
  [["37dc", "common/runtime", "common/vendor"]],
]);

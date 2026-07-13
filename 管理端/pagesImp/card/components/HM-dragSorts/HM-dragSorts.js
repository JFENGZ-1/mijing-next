(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/card/components/HM-dragSorts/HM-dragSorts"],
  {
    "38c1": function (t, i, s) {
      "use strict";
      s.r(i);
      var r = s("6900"),
        e = s("5538");
      for (var o in e)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            s.d(i, t, function () {
              return e[t];
            });
          })(o);
      s("54f0");
      var a = s("828b"),
        n = s("595d"),
        l = Object(a.a)(
          e.default,
          r.b,
          r.c,
          !1,
          null,
          "86ed9c82",
          null,
          !1,
          r.a,
          void 0,
        );
      "function" == typeof n.a && Object(n.a)(l), (i.default = l.exports);
    },
    "54f0": function (t, i, s) {
      "use strict";
      var r = s("9bb1");
      s.n(r).a;
    },
    5538: function (t, i, s) {
      "use strict";
      s.r(i);
      var r = s("8074"),
        e = s.n(r);
      for (var o in r)
        ["default"].indexOf(o) < 0 &&
          (function (t) {
            s.d(i, t, function () {
              return r[t];
            });
          })(o);
      i.default = e.a;
    },
    "595d": function (t, i, s) {
      "use strict";
      i.a = function (t) {
        t.options.wxsCallMethods || (t.options.wxsCallMethods = []),
          t.options.wxsCallMethods.push("loadShadowRow"),
          t.options.wxsCallMethods.push("pageScroll"),
          t.options.wxsCallMethods.push("sort"),
          t.options.wxsCallMethods.push("change"),
          t.options.wxsCallMethods.push("vibrate");
      };
    },
    6900: function (t, i, s) {
      "use strict";
      s.d(i, "b", function () {
        return e;
      }),
        s.d(i, "c", function () {
          return o;
        }),
        s.d(i, "a", function () {
          return r;
        });
      var r = {
          ffValueCard: function () {
            return s
              .e("components/ff-value-card/ff-value-card")
              .then(s.bind(null, "5806"));
          },
          ffCountsCard: function () {
            return s
              .e("components/ff-counts-card/ff-counts-card")
              .then(s.bind(null, "92ca"));
          },
          ffDateCard: function () {
            return s
              .e("components/ff-date-card/ff-date-card")
              .then(s.bind(null, "f24e"));
          },
        },
        e = function () {
          var t = this,
            i =
              (t.$createElement,
              t._self._c,
              0 == t.shadowRow.saleStatus
                ? t.imgsrc("/static/imgs/halt-sales-card.png")
                : null),
            s = t.__map(t.dragList, function (i, s) {
              return {
                $orig: t.__get_orig(i),
                m1:
                  0 == i.saleStatus
                    ? t.imgsrc("/static/imgs/halt-sales-card.png")
                    : null,
              };
            });
          t.$mp.data = Object.assign({}, { $root: { m0: i, l0: s } });
        },
        o = [];
    },
    8074: function (t, i, s) {
      "use strict";
      (function (t) {
        var r = s("47a9");
        Object.defineProperty(i, "__esModule", { value: !0 }),
          (i.default = void 0);
        var e = r(s("af34")),
          o = {
            name: "HM-dragSort",
            components: {
              "ff-value-card": function () {
                s.e("components/ff-value-card/ff-value-card")
                  .then(
                    function () {
                      return resolve(s("5806"));
                    }.bind(null, s),
                  )
                  .catch(s.oe);
              },
              "ff-counts-card": function () {
                s.e("components/ff-counts-card/ff-counts-card")
                  .then(
                    function () {
                      return resolve(s("92ca"));
                    }.bind(null, s),
                  )
                  .catch(s.oe);
              },
              "ff-date-card": function () {
                s.e("components/ff-date-card/ff-date-card")
                  .then(
                    function () {
                      return resolve(s("f24e"));
                    }.bind(null, s),
                  )
                  .catch(s.oe);
              },
            },
            data: function () {
              return {
                guid: "",
                isAppH5: !0,
                shadowRow: {},
                dragList: [],
                ListHeight: this.listHeight,
                scrollViewTop: 0,
                scrollCommand: null,
                isHoldTouch: !1,
                isScrolling: !1,
                scrollAnimation: !1,
                scrollTimer: null,
                wxsDataObj: [],
                wxsDataStr: "[]",
                showDragTips: !1,
                dragTipsTimer: null,
              };
            },
            props: {
              feedbackGenerator: { value: Boolean, default: !0 },
              longTouch: { value: Boolean, default: !1 },
              autoScroll: { value: Boolean, default: !0 },
              longTouchTime: { value: Number, default: 300 },
              list: { value: Array, default: [] },
              rowHeight: { value: Number, default: 44 },
              listHeight: { value: Number, default: 0 },
              listBackgroundColor: { value: String, default: "#fff" },
            },
            watch: {
              longTouch: function (t) {
                this.pushWxsData("longTouch", t);
              },
              longTouchTime: function (t) {
                this.pushWxsData("longTouchTime", t);
              },
              feedbackGenerator: function (t) {
                this.pushWxsData("feedbackGenerator", t);
              },
              autoScroll: function (t) {
                this.pushWxsData("autoScroll", t);
              },
              rowHeight: function (t) {
                this.pushWxsData("rowHeight", t);
              },
              list: {
                handler: function (t) {
                  this.initList(t);
                },
                immediate: !0,
                deep: !0,
              },
              listHeight: {
                handler: function (t) {
                  (this.ListHeight = t),
                    this.pushWxsData("ListHeight", this.ListHeight);
                },
                immediate: !0,
              },
            },
            mounted: function () {
              (this.guid = this.getGuid()),
                getApp().globalData &&
                  getApp().globalData.imgsrc &&
                  (this.imgsrc = getApp().globalData.imgsrc),
                this.showDragTipsFunc();
              var i = t.getSystemInfoSync();
              this.compareVersion(i.hostVersion, "2.14.2") < 0 &&
                console.error(
                  "当前微信基础库:" +
                    i.hostVersion +
                    ",HM-dragSorts组件仅支持微信基础库2.14.2+,请切换基础库!",
                ),
                (this.scrollAnimation = !0),
                (this.isAppH5 = !1),
                0 == this.listHeight && (this.ListHeight = i.windowHeight),
                this.pushWxsData("isAppH5", this.isAppH5),
                this.pushWxsData("ListHeight", this.ListHeight),
                this.pushWxsData("longTouch", this.longTouch);
            },
            beforeDestroy: function () {
              this.dragTipsTimer && clearTimeout(this.dragTipsTimer);
            },
            methods: {
              imgsrc: function (t) {
                return t;
              },
              getGuid: function () {
                function t() {
                  return ((65536 * (1 + Math.random())) | 0)
                    .toString(16)
                    .substring(1);
                }
                return (
                  t() +
                  t() +
                  "_" +
                  t() +
                  "_" +
                  t() +
                  "_" +
                  t() +
                  "_" +
                  t() +
                  t() +
                  t()
                );
              },
              showDragTipsFunc: function () {
                var t = this;
                this.dragTipsTimer && clearTimeout(this.dragTipsTimer),
                  (this.showDragTips = !0),
                  (this.dragTipsTimer = setTimeout(function () {
                    t.showDragTips = !1;
                  }, 5e3));
              },
              initList: function () {
                for (
                  var t = this,
                    i = JSON.parse(JSON.stringify(this.list)),
                    s = 0,
                    r = i.length;
                  s < r;
                  s++
                )
                  i[s].hasOwnProperty("HMDrag_id") ||
                    (i[s].HMDrag_id = "HMDragId_" + this.getGuid()),
                    (i[s].HMDrag_sort = s);
                this.dragList.length > 0
                  ? setTimeout(function () {
                      var s;
                      (s = t.dragList).splice.apply(
                        s,
                        [0, t.dragList.length].concat((0, e.default)(i)),
                      );
                    }, 50)
                  : (this.dragList = JSON.parse(JSON.stringify(i))),
                  this.pushWxsData("lastInitTime", new Date().valueOf());
              },
              loadShadowRow: function (t) {
                this.shadowRow = this.getMoveRow(t.rowSort);
              },
              vibrate: function () {
                t.vibrateShort();
              },
              pageScroll: function (t) {
                var i = this;
                if ("up" == t.command || "down" == t.command) {
                  if (
                    (this.isHoldTouch ||
                      ((this.isHoldTouch = !0),
                      (this.scrollViewTop = t.scrollTop)),
                    this.isScrolling)
                  )
                    return;
                  if (((this.isScrolling = !0), this.isAppH5))
                    return (
                      (t.ListHeight = this.ListHeight),
                      (t.rowHeight = this.rowHeight),
                      (t.rowLength = this.dragList.length),
                      void (this.scrollCommand = t)
                    );
                  null != this.scrollTimer && clearInterval(this.scrollTimer);
                  var s =
                      this.rowHeight * this.dragList.length +
                      1 -
                      this.ListHeight,
                    r = !0;
                  this.scrollTimer = setInterval(function () {
                    r &&
                      (i.runScroll(t.command, s),
                      (r = !1),
                      i.$nextTick(function () {
                        r = !0;
                      }));
                  }, 16.6);
                }
                "stop" == t.command && this.isScrolling && this.stopScroll();
              },
              runScroll: function (t, i) {
                "up" == t && (this.scrollViewTop -= 5),
                  "down" == t && (this.scrollViewTop += 5),
                  this.scrollViewTop < 0 &&
                    ((this.scrollViewTop = 0), clearInterval(this.scrollTimer)),
                  this.scrollViewTop > i &&
                    ((this.scrollViewTop = i), clearInterval(this.scrollTimer));
              },
              stopScroll: function () {
                null != this.scrollTimer && clearInterval(this.scrollTimer),
                  (this.isScrolling = !1),
                  (this.scrollingtop = 0);
              },
              getMoveRow: function (t) {
                for (var i = 0, s = this.dragList.length; i < s; i++)
                  if (this.dragList[i].HMDrag_sort == t)
                    return JSON.parse(JSON.stringify(this.dragList[i]));
              },
              triggerClick: function (t, i) {
                var s = JSON.parse(JSON.stringify(i));
                delete s.HMDrag_id,
                  delete s.HMDrag_sort,
                  this.$emit("onclick", {
                    index: t,
                    row: JSON.parse(JSON.stringify(s)),
                  });
              },
              change: function (t) {
                (t.moveRow = this.getMoveRow(t.index)),
                  delete t.moveRow.HMDrag_id,
                  delete t.moveRow.HMDrag_sort,
                  this.$emit("change", t);
              },
              sort: function (t) {
                this.stopScroll(), (this.isHoldTouch = !1);
                var i = this.getMoveRow(t.index);
                delete i.HMDrag_id, delete i.HMDrag_sort;
                for (
                  var s = JSON.parse(JSON.stringify(this.dragList)),
                    r = [],
                    e = 0,
                    o = s.length;
                  e < o;
                  e++
                ) {
                  delete s[e].HMDrag_id, delete s[e].HMDrag_sort;
                  var a = t.sortArray[e];
                  (this.dragList[e].HMDrag_sort = a), (r[a] = s[e]);
                }
                this.pushNewSort(),
                  this.$emit("confirm", {
                    list: r,
                    index: t.index,
                    moveTo: t.offset,
                    moveRow: i,
                  });
              },
              getNowList: function () {
                for (
                  var t = JSON.parse(JSON.stringify(this.dragList)),
                    i = [],
                    s = 0,
                    r = t.length;
                  s < r;
                  s++
                ) {
                  var e = t[s].HMDrag_sort;
                  (i[e] = t[s]), delete i[e].HMDrag_id, delete i[e].HMDrag_sort;
                }
                return i;
              },
              splice: function () {
                for (
                  var t,
                    i = arguments[0],
                    s = arguments[1],
                    r = arguments.length,
                    e = [],
                    o = 2;
                  o < r;
                  o++
                ) {
                  var a = arguments[o];
                  (a.HMDrag_id = "HMDragId_" + this.getGuid()),
                    (a.HMDrag_sort = i + o - 2),
                    e.push(a);
                }
                for (
                  var n = i,
                    l = s > 0 ? i + s - 1 : i,
                    h = e.length - s,
                    u = this.dragList.length - 1;
                  u >= 0;
                  u--
                ) {
                  var c = this.dragList[u],
                    g = c.HMDrag_sort;
                  if (!(g < n))
                    if (s > 0 && g >= n && g <= l) this.dragList.splice(u, 1);
                    else if (0 != h && g >= l) {
                      var d = g + h;
                      this.dragList[u].HMDrag_sort = d;
                    }
                }
                (t = this.dragList).push.apply(t, e),
                  this.pushNewSort(),
                  JSON.parse(JSON.stringify(this.dragList));
                var f = this.getNowList();
                return f;
              },
              push: function () {
                for (
                  var t,
                    i = arguments.length,
                    s = [],
                    r = this.dragList.length,
                    e = 0;
                  e < i;
                  e++
                ) {
                  var o = arguments[e];
                  (o.HMDrag_id = "HMDragId_" + this.getGuid()),
                    (o.HMDrag_sort = r + e),
                    s.push(o);
                }
                (t = this.dragList).push.apply(t, s), this.pushNewSort();
                var a = this.getNowList();
                return a;
              },
              unshift: function () {
                for (var t, i = arguments.length, s = [], r = 0; r < i; r++) {
                  var e = arguments[r];
                  (e.HMDrag_id = "HMDragId_" + this.getGuid()),
                    (e.HMDrag_sort = r),
                    s.push(e);
                }
                for (var o = this.dragList.length - 1; o >= 0; o--) {
                  var a = this.dragList[o],
                    n = a.HMDrag_sort,
                    l = n + i;
                  this.dragList[o].HMDrag_sort = l;
                }
                (t = this.dragList).push.apply(t, s), this.pushNewSort();
                var h = this.getNowList();
                return h;
              },
              pushNewSort: function () {
                for (var t = [], i = 0, s = this.dragList.length; i < s; i++)
                  t.push(this.dragList[i].HMDrag_sort);
                this.pushWxsData("sortArray", t),
                  this.pushWxsData("lastInitTime", new Date().valueOf());
              },
              pushWxsData: function () {
                var t =
                    arguments.length > 0 && void 0 !== arguments[0]
                      ? arguments[0]
                      : null,
                  i =
                    arguments.length > 1 && void 0 !== arguments[1]
                      ? arguments[1]
                      : null;
                this.wxsDataObj.splice(
                  0,
                  9,
                  ["guid", this.guid],
                  ["listLength", this.dragList.length],
                  ["ListHeight", this.ListHeight],
                  ["rowHeight", this.rowHeight],
                  ["isAppH5", this.isAppH5],
                  ["longTouch", this.longTouch],
                  ["longTouchTime", this.longTouchTime],
                  ["feedbackGenerator", this.feedbackGenerator],
                  ["autoScroll", this.autoScroll],
                );
                for (var s = -1, r = 0; r < this.wxsDataObj.length; r++)
                  if (this.wxsDataObj[r][0] == t) {
                    s = r;
                    break;
                  }
                s > -1
                  ? (this.wxsDataObj[s][1] = i)
                  : (this.wxsDataObj.push([t, i]),
                    "sortArray" == t && this.wxsDataObj.length),
                  "" != this.guid &&
                    (this.wxsDataStr = JSON.stringify(this.wxsDataObj));
              },
              compareVersion: function (t, i) {
                (t = t.split(".")), (i = i.split("."));
                for (var s = Math.max(t.length, i.length); t.length < s; )
                  t.push("0");
                for (; i.length < s; ) i.push("0");
                for (var r = 0; r < s; r++) {
                  var e = parseInt(t[r]),
                    o = parseInt(i[r]);
                  if (e > o) return 1;
                  if (e < o) return -1;
                }
                return 0;
              },
            },
          };
        i.default = o;
      }).call(this, s("df3c").default);
    },
    "9bb1": function (t, i, s) {},
  },
]),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    "pagesImp/card/components/HM-dragSorts/HM-dragSorts-create-component",
    {
      "pagesImp/card/components/HM-dragSorts/HM-dragSorts-create-component":
        function (t, i, s) {
          s("df3c").createComponent(s("38c1"));
        },
    },
    [["pagesImp/card/components/HM-dragSorts/HM-dragSorts-create-component"]],
  ]);

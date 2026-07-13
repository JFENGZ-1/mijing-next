(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pageReport/teacherMembership/personalSalaryFixedCourseAmount"],
  {
    "0224": function (t, i, e) {
      "use strict";
      e.r(i);
      var n = e("f7a0"),
        r = e.n(n);
      for (var s in n)
        ["default"].indexOf(s) < 0 &&
          (function (t) {
            e.d(i, t, function () {
              return n[t];
            });
          })(s);
      i.default = r.a;
    },
    7813: function (t, i, e) {
      "use strict";
      (function (t, i) {
        var n = e("47a9");
        e("86d2"), n(e("3240"));
        var r = n(e("a063"));
        (t.__webpack_require_UNI_MP_PLUGIN__ = e), i(r.default);
      }).call(this, e("3223").default, e("df3c").createPage);
    },
    "7ed7": function (t, i, e) {
      "use strict";
      e.d(i, "b", function () {
        return r;
      }),
        e.d(i, "c", function () {
          return s;
        }),
        e.d(i, "a", function () {
          return n;
        });
      var n = {
          uButton: function () {
            return e
              .e("uview-ui/components/u-button/u-button")
              .then(e.bind(null, "d5d3"));
          },
          ffBottomLogo: function () {
            return e
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(e.bind(null, "3111"));
          },
        },
        r = function () {
          var t = this,
            i =
              (t.$createElement, t._self._c, t.$shorten(t.user.staffName, 15)),
            e = t.imgsrc("imgs/202506/kjtx.png"),
            n = t.__map(t.tlist, function (i, e) {
              return {
                $orig: t.__get_orig(i),
                m2: t.$shorten(i.courseName, 9),
                m3: i.tagData && i.tagData ? t.$shorten(i.tagData, 3) : null,
                m4: t.$shorten(i.staffName, 2),
              };
            }),
            r = t.plist && t.plist.length > 0;
          t._isMounted ||
            ((t.e0 = function (i) {
              return t.$refs.quickfillRef.open();
            }),
            (t.e1 = function (t) {
              t.stopPropagation();
            }),
            (t.e2 = function (t) {
              t.stopPropagation();
            }),
            (t.e3 = function (t) {
              t.stopPropagation();
            })),
            (t.$mp.data = Object.assign(
              {},
              { $root: { m0: i, m1: e, l0: n, g0: r } },
            ));
        },
        s = [];
    },
    "81a6": function (t, i, e) {},
    a063: function (t, i, e) {
      "use strict";
      e.r(i);
      var n = e("7ed7"),
        r = e("0224");
      for (var s in r)
        ["default"].indexOf(s) < 0 &&
          (function (t) {
            e.d(i, t, function () {
              return r[t];
            });
          })(s);
      e("bc11");
      var a = e("828b"),
        o = Object(a.a)(
          r.default,
          n.b,
          n.c,
          !1,
          null,
          "44f963aa",
          null,
          !1,
          n.a,
          void 0,
        );
      i.default = o.exports;
    },
    bc11: function (t, i, e) {
      "use strict";
      var n = e("81a6");
      e.n(n).a;
    },
    f7a0: function (t, i, e) {
      "use strict";
      (function (t) {
        var n = e("47a9");
        Object.defineProperty(i, "__esModule", { value: !0 }),
          (i.default = void 0);
        var r,
          s = n(e("7ca3")),
          a = n(e("3387")),
          o = e("4689"),
          c = {
            data: function () {
              return {
                allSelected: !1,
                title: "课费提成设置",
                plist: [],
                tlist: [],
                user: {},
                courseName: !0,
                custom_style: {
                  width: "458rpx",
                  height: "83rpx",
                  backgroundColor: "#FBD128",
                  fontSize: "32rpx",
                  borderRadius: "41rpx",
                  border: "none",
                  color: "#181818",
                },
              };
            },
            components: {
              navigation: function () {
                e.e("components/navigation/index")
                  .then(
                    function () {
                      return resolve(e("af9e"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
              quickfill: function () {
                e.e("pageReport/component/quickfillCourseAmount")
                  .then(
                    function () {
                      return resolve(e("81b5"));
                    }.bind(null, e),
                  )
                  .catch(e.oe);
              },
            },
            computed: {
              StatusBar: function () {
                return this.$store.state.systemInfo.statusBarHeight;
              },
              CustomBar: function () {
                var i = t.getMenuButtonBoundingClientRect();
                return (
                  i.height +
                  2 * (i.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                  2
                );
              },
            },
            methods:
              ((r = {
                quickfillSubmit: function (t) {
                  this.tlist.forEach(function (i) {
                    (i.selected = !0),
                      (i.unitPrice = t.unitPrice + "元"),
                      (i.additionalPrice = t.additionalPrice);
                  }),
                    this.$forceUpdate();
                },
                handlePrivateInputFocus: function (t) {
                  this.plist[t].unitPrice &&
                    (this.plist[t].unitPrice = this.plist[t].unitPrice.replace(
                      "元",
                      "",
                    ));
                },
                handlePrivateInputBlur: function (i) {
                  if (this.plist[i].unitPrice) {
                    var e = this.plist[i].unitPrice.replace("元", "");
                    if (!this.isPositiveInteger(e))
                      return (
                        t.showToast({
                          title: "".concat(
                            this.plist[i].courseName,
                            "的课费必须为正整数",
                          ),
                          icon: "none",
                        }),
                        void (this.plist[i].unitPrice = "")
                      );
                    this.plist[i].unitPrice = e + "元";
                  }
                  this.$forceUpdate();
                },
                handleAllSelect: function (t) {
                  this.tlist.forEach(function (i) {
                    i.selected = t.value;
                  });
                },
                handleInputFocus: function (t) {
                  this.tlist[t].unitPrice &&
                    (this.tlist[t].unitPrice = this.tlist[t].unitPrice.replace(
                      "元",
                      "",
                    ));
                },
                handleInputadditionalPriceFocus: function (t) {
                  this.tlist[t].additionalPrice &&
                    (this.tlist[t].additionalPrice = this.tlist[
                      t
                    ].additionalPrice.replace("元", ""));
                },
                handleItemSelect: function (t) {
                  var i = this;
                  this.$nextTick(function () {
                    var t = i.tlist.every(function (t) {
                      return t.selected;
                    });
                    i.allSelected = t;
                  });
                },
                isPositiveInteger: function (t, i) {
                  var e = String(t).trim();
                  return new RegExp(
                    "^(0|[1-9]\\d{0,".concat(i - 1, "})$"),
                  ).test(e);
                },
                save: function () {
                  var i = this,
                    e = a.default.cloneDeep(this.plist),
                    n = a.default.cloneDeep(this.tlist),
                    r = !0,
                    s = n.find(function (t) {
                      if (t.selected) {
                        var e = t.unitPrice
                            ? t.unitPrice.replace("元", "")
                            : "",
                          n = t.additionalPrice;
                        if (null == e || !i.isPositiveInteger(e, 4))
                          return (
                            (t.title = "".concat(
                              t.courseName,
                              "的基础课费必须为正整数，且不能超过4位",
                            )),
                            (r = !1),
                            !0
                          );
                        if (null == n || !i.isPositiveInteger(n, 2))
                          return (
                            (t.title = "".concat(
                              t.courseName,
                              "的耗课提成必须为正整数，且不能超过2位",
                            )),
                            (r = !1),
                            !0
                          );
                      }
                      return !1;
                    });
                  if (s) t.showToast({ title: s.title, icon: "none" });
                  else {
                    var c = e.find(function (t) {
                      if (t.selected) {
                        var e = t.unitPrice
                            ? t.unitPrice.replace("元", "")
                            : "",
                          n = t.additionalPrice;
                        if (!e || !i.isPositiveInteger(e, 4))
                          return (
                            (t.title = "".concat(
                              t.courseName,
                              "的基础课费必须为正整数，且不能超过4位",
                            )),
                            (r = !1),
                            !0
                          );
                        if (!n || !i.isPositiveInteger(n, 2))
                          return (
                            (t.title = "".concat(
                              t.courseName,
                              "的耗课提成必须为正整数，且不能超过2位",
                            )),
                            (r = !1),
                            !0
                          );
                      }
                      return !1;
                    });
                    c && t.showToast({ title: c.title, icon: "none" });
                  }
                  if (r) {
                    var u = a.default.cloneDeep(this.plist),
                      l = a.default.cloneDeep(this.tlist).filter(function (t) {
                        if (t.selected) {
                          var i = t.unitPrice
                            ? t.unitPrice.replace("元", "")
                            : "";
                          return (
                            (t.unitPrice = i),
                            (t.additionalPrice = t.additionalPrice),
                            !0
                          );
                        }
                        return !1;
                      }),
                      f = u.filter(function (t) {
                        if (t.selected) {
                          var i = t.unitPrice
                            ? t.unitPrice.replace("元", "")
                            : "";
                          return (
                            (t.unitPrice = i),
                            (t.additionalPrice = t.additionalPrice),
                            !0
                          );
                        }
                        return !1;
                      });
                    if (0 === l.length && 0 === f.length)
                      return void t.showToast({
                        title: "请至少填写一个课程并设置课费",
                        icon: "none",
                      });
                    var d = {
                      staffUserid: this.user.staffUserid,
                      tlist: l,
                      plist: f,
                    };
                    (0, o.saveSalaryConfigOfOneStaff)(d).then(function (i) {
                      200 == i.code
                        ? t.showToast({
                            title: "保存成功",
                            icon: "none",
                            mask: !0,
                            success: function () {
                              setTimeout(function () {
                                t.navigateBack();
                              }, 500);
                            },
                          })
                        : t.showToast({ title: i.msg, icon: "none", mask: !0 });
                    });
                  }
                },
                handleadditionalPriceInputBlur: function (i) {
                  if (this.tlist[i].additionalPrice) {
                    var e = this.tlist[i].additionalPrice;
                    if (!this.isPositiveInteger(e, 2))
                      return (
                        t.showToast({
                          title: "".concat(
                            this.tlist[i].courseName,
                            "的每节耗课提成必须为正整数却不能超过2位",
                          ),
                          icon: "none",
                        }),
                        void (this.tlist[i].additionalPrice = "")
                      );
                    this.tlist[i].additionalPrice = e + "元";
                  }
                  this.$forceUpdate();
                },
                handleInputBlur: function (i) {
                  if (this.tlist[i].unitPrice) {
                    var e = this.tlist[i].unitPrice.replace("元", "");
                    if (!this.isPositiveInteger(e, 4))
                      return (
                        t.showToast({
                          title: "".concat(
                            this.tlist[i].courseName,
                            "的课费必须为正整数",
                          ),
                          icon: "none",
                        }),
                        (this.tlist[i].selected = !1),
                        void (this.tlist[i].unitPrice = "")
                      );
                    (this.tlist[i].selected = !0),
                      (this.tlist[i].unitPrice = e + "元");
                  } else this.tlist[i].selected = !1;
                  this.$forceUpdate();
                },
              }),
              (0, s.default)(r, "handlePrivateInputBlur", function (i) {
                if (this.plist[i].unitPrice) {
                  var e = this.plist[i].unitPrice.replace("元", "");
                  if (!this.isPositiveInteger(e, 4))
                    return (
                      t.showToast({
                        title: "".concat(
                          this.plist[i].courseName,
                          "的课费必须为正整数",
                        ),
                        icon: "none",
                      }),
                      void (this.plist[i].unitPrice = "")
                    );
                  this.plist[i].unitPrice = e + "元";
                }
                this.$forceUpdate();
              }),
              (0, s.default)(r, "getInit", function () {
                var t = this;
                (0, o.getSalaryConfigOfOneStaff)({
                  staffUserid: this.user.staffUserid,
                }).then(function (i) {
                  (t.plist = i.data.plist),
                    (t.tlist = i.data.tlist),
                    (t.user.staffFace = i.data.staffFace),
                    (t.user.staffName = i.data.staffName),
                    t.tlist.forEach(function (t, i) {
                      t.selected &&
                        ((t.unitPrice = t.unitPrice + "元"),
                        (t.additionalPrice = t.additionalPrice));
                    }),
                    (t.allSelected = t.tlist.every(function (t) {
                      return t.selected;
                    })),
                    t.plist.forEach(function (i, e) {
                      i.courseName ? (t.courseName = !0) : (t.courseName = !1),
                        i.selected &&
                          ((i.unitPrice = i.unitPrice + "元"),
                          (i.additionalPrice = i.additionalPrice));
                    });
                });
              }),
              r),
            watch: {
              plist: {
                handler: function (t) {
                  t.forEach(function (t) {
                    t.unitPrice ? (t.selected = !0) : (t.selected = !1);
                  });
                },
                deep: !0,
              },
            },
            onLoad: function (t) {
              (this.user.staffUserid = t.staffUserid), this.getInit();
            },
          };
        i.default = c;
      }).call(this, e("df3c").default);
    },
  },
  [["7813", "common/runtime", "common/vendor"]],
]);

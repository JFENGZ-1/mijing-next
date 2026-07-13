(global.webpackJsonp = global.webpackJsonp || []).push([
  ["pagesImp/shop/setting/store/store-setting"],
  {
    "2abe": function (e, t, i) {
      "use strict";
      i.r(t);
      var n = i("68e1"),
        o = i.n(n);
      for (var s in n)
        ["default"].indexOf(s) < 0 &&
          (function (e) {
            i.d(t, e, function () {
              return n[e];
            });
          })(s);
      t.default = o.a;
    },
    "30a1": function (e, t, i) {
      "use strict";
      var n = i("3c5c");
      i.n(n).a;
    },
    3856: function (e, t, i) {
      "use strict";
      i.d(t, "b", function () {
        return o;
      }),
        i.d(t, "c", function () {
          return s;
        }),
        i.d(t, "a", function () {
          return n;
        });
      var n = {
          zeroLoading: function () {
            return i
              .e("components/zero-loading/zero-loading")
              .then(i.bind(null, "f7e3"));
          },
          uForm: function () {
            return i
              .e("uview-ui/components/u-form/u-form")
              .then(i.bind(null, "a809"));
          },
          uFormItem: function () {
            return Promise.all([
              i.e("common/vendor"),
              i.e("uview-ui/components/u-form-item/u-form-item"),
            ]).then(i.bind(null, "ec61"));
          },
          uIcon: function () {
            return i
              .e("uview-ui/components/u-icon/u-icon")
              .then(i.bind(null, "81af"));
          },
          uLine: function () {
            return i
              .e("uview-ui/components/u-line/u-line")
              .then(i.bind(null, "fac3"));
          },
          uInput: function () {
            return Promise.all([
              i.e("common/vendor"),
              i.e("uview-ui/components/u-input/u-input"),
            ]).then(i.bind(null, "b5ea"));
          },
          uSelect: function () {
            return i
              .e("uview-ui/components/u-select/u-select")
              .then(i.bind(null, "decf"));
          },
          uButton: function () {
            return i
              .e("uview-ui/components/u-button/u-button")
              .then(i.bind(null, "d5d3"));
          },
          timePopup: function () {
            return Promise.all([
              i.e("common/vendor"),
              i.e("components/time-popup/time-popup"),
            ]).then(i.bind(null, "6052"));
          },
          ffBottomLogo: function () {
            return i
              .e("components/ff-bottom-logo/ff-bottom-logo")
              .then(i.bind(null, "3111"));
          },
        },
        o = function () {
          var e = this;
          e.$createElement;
          e._self._c,
            e._isMounted ||
              ((e.e0 = function (t) {
                e.show = !0;
              }),
              (e.e1 = function (t) {
                e.show = !0;
              }));
        },
        s = [];
    },
    "3c5c": function (e, t, i) {},
    "68e1": function (e, t, i) {
      "use strict";
      (function (e, n) {
        var o = i("47a9");
        Object.defineProperty(t, "__esModule", { value: !0 }),
          (t.default = void 0);
        var s = o(i("7ca3")),
          u = i("f24f"),
          a = o(i("7502"));
        function r(e, t) {
          var i = Object.keys(e);
          if (Object.getOwnPropertySymbols) {
            var n = Object.getOwnPropertySymbols(e);
            t &&
              (n = n.filter(function (t) {
                return Object.getOwnPropertyDescriptor(e, t).enumerable;
              })),
              i.push.apply(i, n);
          }
          return i;
        }
        function c(e) {
          for (var t = 1; t < arguments.length; t++) {
            var i = null != arguments[t] ? arguments[t] : {};
            t % 2
              ? r(Object(i), !0).forEach(function (t) {
                  (0, s.default)(e, t, i[t]);
                })
              : Object.getOwnPropertyDescriptors
                ? Object.defineProperties(
                    e,
                    Object.getOwnPropertyDescriptors(i),
                  )
                : r(Object(i)).forEach(function (t) {
                    Object.defineProperty(
                      e,
                      t,
                      Object.getOwnPropertyDescriptor(i, t),
                    );
                  });
          }
          return e;
        }
        var f = {
          data: function () {
            var e = this;
            return {
              isBTloading: !1,
              areaList: [],
              title: "场馆信息",
              dayDate: [
                {
                  weekNum: 1,
                  status: "uncheck",
                  imagestatus: "imageuncheck",
                  value: "周一",
                },
                {
                  weekNum: 2,
                  status: "uncheck",
                  imagestatus: "imageuncheck",
                  value: "周二",
                },
                {
                  weekNum: 3,
                  status: "uncheck",
                  imagestatus: "imageuncheck",
                  value: "周三",
                },
                {
                  weekNum: 4,
                  status: "uncheck",
                  imagestatus: "imageuncheck",
                  value: "周四",
                },
                {
                  weekNum: 5,
                  status: "uncheck",
                  imagestatus: "imageuncheck",
                  value: "周五",
                },
                {
                  weekNum: 6,
                  status: "uncheck",
                  imagestatus: "imageuncheck",
                  value: "周六",
                },
                {
                  weekNum: 7,
                  status: "uncheck",
                  imagestatus: "imageuncheck",
                  value: "周日",
                },
              ],
              imeviewlist: {},
              showTime: !1,
              siteinfo: {},
              opentime: [
                {
                  timeValue: "00:00~24:00",
                  weekValue: "周一至周日",
                  weeknum: "1234567",
                },
              ],
              datalist: [],
              show: !1,
              provinceList: [],
              city: [],
              county: [],
              region: [0, 0, -1],
              saveBtnStyle: {
                height: "84rpx",
                background: "#FBD128",
                fontSize: "32rpx",
                color: "#181818",
                margin: " 82rpx 146rpx 0rpx 146rpx",
              },
              inputStyle: {
                paddingLeft: "23rpx",
                margin: "0rpx 0rpx",
                color: "#7E7E7E",
                width: "450rpx",
              },
              righticonstyle: { color: "#7E7E7E" },
              labelStyle: { width: "120rpx" },
              errorType: ["toast"],
              rules: {
                siteTrademark: [{ required: !0, message: "请上传店标" }],
                siteName: [
                  { required: !0, max: 20, message: "店名不能超过20个字" },
                ],
                siteTel: [
                  { required: !0, message: "请输入联系方式" },
                  {
                    validator: function (t, i, n) {
                      return e.$u.test.mobile(i.replace(/\s/g, ""));
                    },
                    message: "联系方式输入不正确",
                  },
                ],
                citiesProvinceName: [{ required: !0, message: "请选择省份" }],
                siteAddr: [
                  {
                    required: !0,
                    max: 30,
                    message: "请输入地址不能超过30个字",
                  },
                ],
              },
              top: null,
              background: "#FBD128",
              item: { isShowHandelSelect: !1 },
              pageLoading: !0,
            };
          },
          components: {
            navigation: function () {
              i.e("components/navigation/index")
                .then(
                  function () {
                    return resolve(i("af9e"));
                  }.bind(null, i),
                )
                .catch(i.oe);
            },
          },
          created: function () {
            var t = this;
            e.$on("uAvatarCropper", function (i) {
              (t.avatar = i),
                e.uploadFile({
                  url: "".concat(a.default.baseUrl, "/common/uploadfile"),
                  filePath: i,
                  name: "file",
                  complete: function (e) {
                    var i = JSON.parse(e.data).dbUrl,
                      n = JSON.parse(e.data).webUrl;
                    t.$set(t.siteinfo, "siteTrademark", i),
                      t.$set(t.siteinfo, "siteTrademarkweb", n);
                  },
                });
            });
          },
          onUnload: function () {
            e.$off("uAvatarCropper");
          },
          computed: {
            StatusBar: function () {
              return this.$store.state.systemInfo.statusBarHeight;
            },
            CustomBar: function () {
              var t = e.getMenuButtonBoundingClientRect();
              return (
                t.height +
                2 * (t.top - (0 !== this.StatusBar ? this.StatusBar : 20)) +
                2
              );
            },
          },
          methods: {
            chooseAvatar: function () {
              this.$u.route({
                url: "/uview-ui/components/u-avatar-cropper/u-avatar-cropper",
                params: { destWidth: 300, rectWidth: 350, fileType: "jpg" },
              });
            },
            hideDown: function () {
              this.datalist.forEach(function (e) {
                e.isShowHandelSelect = !1;
              });
            },
            uploadhead: function () {
              document.getElementsByClassName("slot-btn").onclick;
            },
            removeitem: function (e) {
              this.opentime = this.opentime.filter(function (t) {
                return t != e;
              });
            },
            getConst: function () {
              var e = this;
              (0, u.getConst)().then(function (t) {
                (e.provinceList = t.province),
                  (e.city = t.city),
                  (e.county = t.county),
                  (e.areaList = e.buildTree(t.province, t.city, t.county));
                var i = e.areaList.shift();
                if (
                  (e.areaList.push(i),
                  e.siteinfo && e.siteinfo.citiesProvinceCode)
                ) {
                  var n = e.areaList.findIndex(function (t) {
                      return t.value == e.siteinfo.citiesProvinceCode;
                    }),
                    o = e.areaList.find(function (t) {
                      return t.value == e.siteinfo.citiesProvinceCode;
                    }),
                    s = o.children.findIndex(function (t) {
                      return t.value == e.siteinfo.citiesCityCode;
                    }),
                    u = o.children
                      .find(function (t) {
                        return t.value == e.siteinfo.citiesCityCode;
                      })
                      .children.findIndex(function (t) {
                        return t.value == e.siteinfo.citiesCountyCode;
                      });
                  e.region = -1 == u ? [n, s, -1] : [n, s, u];
                }
              });
            },
            loadStore: function (e) {
              var t = this,
                i = this,
                n = {};
              (n.siteId = e),
                (0, u.getSiteInfo)(n).then(function (e) {
                  (i.siteinfo = e.siteinfo),
                    (i.opentime = e.opentime),
                    t.getConst();
                });
            },
            buildTree: function (e, t, i) {
              var n = e.reduce(function (e, t) {
                  return (
                    (e[t.citiesProvinceCode] = {
                      value: t.citiesProvinceCode,
                      label: t.citiesProvinceName,
                      children: [],
                    }),
                    e
                  );
                }, {}),
                o = t.reduce(function (e, t) {
                  var i = {
                    value: t.citiesCityCode,
                    label: t.citiesCityName,
                    children: [],
                  };
                  e[t.citiesCityCode] = i;
                  var o = n[t.citiesProvinceCode];
                  return o && o.children.push(i), e;
                }, {});
              return (
                i.forEach(function (e) {
                  var t = {
                      value: e.citiesCountyCode,
                      label: e.citiesCountyName,
                    },
                    i = o[e.citiesCityCode];
                  i && i.children.push(t);
                }),
                Object.values(n)
              );
            },
            confirm: function (e) {
              var t = this;
              e.forEach(function (e, i) {
                0 == i &&
                  ((t.siteinfo.citiesProvinceName = e.label),
                  (t.siteinfo.citiesProvinceCode = e.value)),
                  1 == i &&
                    ((t.siteinfo.citiesCityName = e.label),
                    (t.siteinfo.citiesCityCode = e.value)),
                  2 == i &&
                    ((t.siteinfo.citiesCountyName = e.label || ""),
                    (t.siteinfo.citiesCountyCode = e.value || ""));
              });
            },
            openPopup: function (e) {
              var t = this;
              e
                ? ((this.imeviewlist = e),
                  (this.imeviewlist.timeValueArray = e.timeValue.split(",")),
                  "00:00~24:00" == this.imeviewlist.timeValue &&
                    (this.imeviewlist.timeValue24 = !0))
                : ((this.imeviewlist = {}),
                  (this.imeviewlist.timeValue = []),
                  (this.imeviewlist.timeValueArray = [])),
                this.disDay(e),
                (this.showTime = !1),
                this.dayDate.forEach(function (e) {
                  "check" != e.status || (t.showTime = !0);
                }),
                this.$refs.child.open(this.dayDate, this.imeviewlist);
            },
            disDay: function (e) {
              var t = this;
              if (
                (this.dayDate.forEach(function (e) {
                  (e.status = "uncheck"), (e.imagestatus = "imageuncheck");
                }),
                this.opentime && this.opentime.length > 0)
              ) {
                var i = [];
                e &&
                  (this.dayDate.forEach(function (t) {
                    e.weeknum.indexOf(t.weekNum) >= 0 &&
                      ((t.status = "check"), (t.imagestatus = "imagecheck"));
                  }),
                  i.push(e.weeknum)),
                  this.opentime
                    .filter(function (e) {
                      return !i.some(function (t) {
                        return t == e.weeknum;
                      });
                    })
                    .forEach(function (e) {
                      t.dayDate.forEach(function (t) {
                        e.weeknum.indexOf(t.weekNum) >= 0 &&
                          ((t.status = "discheck"),
                          (t.imagestatus = "imagedischeck"));
                      });
                    });
              }
            },
            saveWeekTime: function (e) {
              if (this.opentime || 0 != this.opentime.length) {
                var t = this.opentime.filter(function (t) {
                  return t.nnid && e.nnid != t.nnid;
                });
                t.push(e), (this.opentime = t), this.$forceUpdate();
              } else this.opentime.push(e);
            },
            saveSite: function () {
              var t = this;
              this.$refs.usiteinfo.validate(function (i) {
                if (!i) return (t.isBTloading = !1), !1;
                if (null != t.opentime && 0 != t.opentime.length) {
                  var o = {};
                  if (
                    (t.siteinfo.siteId && (o.siteId = t.siteinfo.siteId),
                    (o.openTimeList = []),
                    t.opentime.forEach(function (e) {
                      var t = {};
                      (t.timeValue = e.timeValue),
                        (t.weeknum = e.weeknum),
                        o.openTimeList.push(t);
                    }),
                    (o.siteName = t.siteinfo.siteName),
                    -1 == t.siteinfo.siteTrademark.indexOf("http") &&
                      (o.siteTrademark = t.siteinfo.siteTrademark),
                    (o.siteTel = t.siteinfo.siteTel.replace(/\s/g, "")),
                    (o.siteAddr = t.siteinfo.siteAddr),
                    (o.citiesProvinceCode = t.siteinfo.citiesProvinceCode),
                    (o.citiesCityCode = t.siteinfo.citiesCityCode),
                    (o.citiesCountyCode = t.siteinfo.citiesCountyCode),
                    !t.savedis)
                  ) {
                    (t.isBTloading = !0),
                      n.showLoading({ title: "正在保存", mask: !0 });
                    var s = e.getStorageSync("authorizationInfo");
                    (o.headFaceurl = s ? s.avatarUrl : ""),
                      (o.nickName = s ? s.nickname : ""),
                      (o.weixinPhone = s ? s.userphone : ""),
                      (0, u.saveSiteInfo)(o)
                        .then(function (i) {
                          if ((e.hideLoading(), 200 == i.code)) {
                            if (i.loginInfo) {
                              var n = i.loginInfo,
                                o = n.site,
                                s = n.hasSiteCount,
                                u = n.isVisitor,
                                a = c(
                                  c({}, o),
                                  {},
                                  { hasSiteCount: s, isVisitor: u },
                                );
                              t.$store.commit("SET_STOPINFO", a),
                                e.setStorageSync("stopInfo", a);
                            }
                            e.showToast({
                              title: "保存成功",
                              icon: "none",
                              success: function () {
                                setTimeout(function () {
                                  e.navigateBack(), e.$emit("shopInfoOk");
                                }, 1e3);
                              },
                            });
                          } else
                            (t.isBTloading = !1),
                              e.showToast({ title: i.msg, icon: "none" });
                        })
                        .catch(function (i) {
                          e.hideLoading(),
                            (t.isBTloading = !1),
                            e.showToast({
                              title: "保存失败，请重试",
                              icon: "none",
                            });
                        });
                  }
                } else e.showToast({ title: "请选择营业时间！", icon: "none" });
              });
            },
          },
          onLoad: function (t) {
            if (((this.showTime = !1), t && "storesManagement" == t.id)) {
              this.siteinfo = {};
              var i = e.getStorageSync("authorizationInfo");
              i && i.userphone && (this.siteinfo.siteTel = i.userphone),
                this.getConst();
            } else
              t && "storesManagement" != t.id
                ? this.loadStore(t.id)
                : this.loadStore();
            this.pageLoading = !1;
          },
          onReady: function () {
            this.$refs.usiteinfo.setRules(this.rules);
          },
        };
        t.default = f;
      }).call(this, i("df3c").default, i("3223").default);
    },
    f19d: function (e, t, i) {
      "use strict";
      (function (e, t) {
        var n = i("47a9");
        i("86d2"), n(i("3240"));
        var o = n(i("fcf3"));
        (e.__webpack_require_UNI_MP_PLUGIN__ = i), t(o.default);
      }).call(this, i("3223").default, i("df3c").createPage);
    },
    fcf3: function (e, t, i) {
      "use strict";
      i.r(t);
      var n = i("3856"),
        o = i("2abe");
      for (var s in o)
        ["default"].indexOf(s) < 0 &&
          (function (e) {
            i.d(t, e, function () {
              return o[e];
            });
          })(s);
      i("30a1");
      var u = i("828b"),
        a = Object(u.a)(
          o.default,
          n.b,
          n.c,
          !1,
          null,
          "a5460ff6",
          null,
          !1,
          n.a,
          void 0,
        );
      t.default = a.exports;
    },
  },
  [["f19d", "common/runtime", "common/vendor"]],
]);

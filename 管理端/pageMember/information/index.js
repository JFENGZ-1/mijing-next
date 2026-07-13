require("../common/vendor.js"),
  (global.webpackJsonp = global.webpackJsonp || []).push([
    ["pageMember/information/index"],
    {
      "1c09": function (e, t, r) {
        "use strict";
        r.r(t);
        var i = r("e7ec"),
          s = r("8049");
        for (var n in s)
          ["default"].indexOf(n) < 0 &&
            (function (e) {
              r.d(t, e, function () {
                return s[e];
              });
            })(n);
        r("fcb5");
        var o = r("828b"),
          u = Object(o.a)(
            s.default,
            i.b,
            i.c,
            !1,
            null,
            "4ddb92b8",
            null,
            !1,
            i.a,
            void 0,
          );
        t.default = u.exports;
      },
      5582: function (e, t, r) {
        "use strict";
        (function (e) {
          var i = r("47a9");
          Object.defineProperty(t, "__esModule", { value: !0 }),
            (t.default = void 0);
          var s = r("d415"),
            n = r("f24f"),
            o = i(r("7502")),
            u = {
              components: {
                navigation: function () {
                  r.e("components/navigation/index")
                    .then(
                      function () {
                        return resolve(r("af9e"));
                      }.bind(null, r),
                    )
                    .catch(r.oe);
                },
                Remarks: function () {
                  r.e("pageMember/components/remarks")
                    .then(
                      function () {
                        return resolve(r("b36b"));
                      }.bind(null, r),
                    )
                    .catch(r.oe);
                },
                confirm: function () {
                  r.e("components/confirm-modal/confirm-modal")
                    .then(
                      function () {
                        return resolve(r("4e5b"));
                      }.bind(null, r),
                    )
                    .catch(r.oe);
                },
                membershipConsultant: function () {
                  Promise.all([
                    r.e("common/vendor"),
                    r.e("pageMember/components/membershipConsultant"),
                  ])
                    .then(
                      function () {
                        return resolve(r("a8b7"));
                      }.bind(null, r),
                    )
                    .catch(r.oe);
                },
              },
              data: function () {
                return {
                  isShowSelector: !1,
                  defaultSelector: 0,
                  show: !1,
                  timeShow: !1,
                  delConfirmModal: !1,
                  checked: !1,
                  userId: null,
                  personalTainerInfo: null,
                  avatar: "",
                  userField: [],
                  errorStatus: !1,
                  errorMsgList: [],
                  explainText: "",
                  explainTextNum: 0,
                  dbUrl: "",
                  selectorList: { fieldID: "", list: [] },
                  salestaffuserName: "",
                  form: {
                    userPhone: "",
                    userIdent: "",
                    userRealname: "",
                    userHeight: "",
                    userWeight: "",
                    userRemark: "",
                    userSex: "",
                    userBirthday: "",
                    userId: "",
                    saleStaffUserid: "",
                  },
                  selector: ["女", "男"],
                  params: { year: !0, month: !0, day: !0 },
                  defaultTime: "",
                  delChecked: !0,
                };
              },
              created: function () {
                var t = this;
                e.$on("uAvatarCropper", function (r) {
                  (t.avatar = r),
                    e.uploadFile({
                      url: "".concat(o.default.baseUrl, "/common/uploadfile"),
                      filePath: r,
                      name: "file",
                      complete: function (e) {
                        t.dbUrl = JSON.parse(e.data).dbUrl;
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
                headleStatus: function () {
                  this.delChecked = !this.delChecked;
                },
                checkUserField: function (e, t) {
                  var r = this.userField.find(function (t) {
                    return t.id == e;
                  });
                  return t
                    ? !!r && 2 == r.value
                    : !!r && (1 == r.value || 2 == r.value);
                },
                chooseAvatar: function () {
                  if (!this.userId) return !1;
                  this.$u.route({
                    url: "/uview-ui/components/u-avatar-cropper/u-avatar-cropper",
                    params: { destWidth: 200, rectWidth: 350, fileType: "jpg" },
                  });
                },
                getlist: function (e) {
                  var t = this;
                  (0, s.getUserCardInfo)(e).then(function (e) {
                    (t.personalTainerInfo = e.user),
                      (t.userField = e.userField),
                      (t.avatar = e.user.userFaceurl),
                      t.userId &&
                        ((t.salestaffuserName = e.user.salestaffuserName),
                        (t.form = {
                          userId: t.personalTainerInfo.userId,
                          userPhone: t.personalTainerInfo.userPhone,
                          userIdent: t.personalTainerInfo.userIdent,
                          userRealname: t.personalTainerInfo.userRealname,
                          userHeight: t.personalTainerInfo.userHeight,
                          userWeight: t.personalTainerInfo.userWeight,
                          userRemark: t.personalTainerInfo.userRemark,
                          userSex: t.personalTainerInfo.userSex,
                          userBirthday: t.personalTainerInfo.userBirthday,
                          saleStaffUserid: t.personalTainerInfo.saleStaffUserid,
                        }),
                        (t.defaultTime = t.form.userBirthday));
                  });
                },
                getUserList: function () {
                  var e = this;
                  (0, s.getuserFieldSetting)().then(function (t) {
                    e.userField = t.configlist;
                  });
                },
                getMenberList: function () {
                  (0, n.findAllUser)({ pageNo: 1, pagesize: 9999 }).then(
                    function (e) {},
                  );
                },
                headlePicker: function (e) {
                  var t = this;
                  if (
                    ((this.isShowSelector = !0), this.selectorList.fieldID != e)
                  ) {
                    if (6 == e) {
                      (this.selectorList.fieldID = 6),
                        (this.selectorList.list = []);
                      for (var r = 100; r < 200; r++)
                        this.selectorList.list.push("".concat(r, "cm"));
                      if (this.form.userHeight) {
                        var i = this.selectorList.list.findIndex(function (e) {
                          return e === t.form.userHeight;
                        });
                        this.defaultSelector = i;
                      } else this.defaultSelector = 60;
                    }
                    if (7 == e) {
                      (this.selectorList.fieldID = 7),
                        (this.selectorList.list = []);
                      for (var s = 20; s < 120; s++)
                        this.selectorList.list.push("".concat(s, "kg"));
                      if (this.form.userWeight) {
                        var n = this.selectorList.list.findIndex(function (e) {
                          return e === t.form.userWeight;
                        });
                        this.defaultSelector = n;
                      } else this.defaultSelector = 30;
                    }
                  }
                },
                pickerCallback: function (e) {
                  6 == this.selectorList.fieldID &&
                    ((this.form.userHeight = this.selectorList.list[e[0]]),
                    (this.defaultSelector = [e[0]])),
                    7 == this.selectorList.fieldID &&
                      ((this.form.userWeight = this.selectorList.list[e[0]]),
                      (this.defaultSelector = [e[0]]));
                },
                headleSex: function () {
                  this.show = !0;
                },
                Sex: function (e) {
                  var t = e[0];
                  this.form.userSex = 0 == t ? t + 2 : e[0];
                },
                headleBirthday: function () {
                  (this.timeShow = !0),
                    this.form.userBirthday
                      ? (this.defaultTime = this.form.userBirthday)
                      : (this.defaultTime = "2000-01-01");
                },
                Birthday: function (e) {
                  var t = e.year,
                    r = e.month,
                    i = e.day;
                  (this.form.userBirthday = t + "-" + r + "-" + i),
                    (this.defaultTime = this.form.userBirthday);
                },
                headleMembershipConsultant: function () {
                  this.$refs.membershipConsultantRef.open();
                },
                membershipConsultant: function (e) {
                  this.$set(this.form, "saleStaffUserid", e.staffUserid),
                    (this.salestaffuserName = e.staffName);
                },
                headleRemarks: function () {
                  this.$refs.remarksRef.open();
                },
                remarksSubmit: function (e) {
                  this.form.userRemark = e;
                },
                headlePreservation: function () {
                  var t = this;
                  try {
                    if (
                      ((this.form.userPhone = this.form.userPhone.replace(
                        /\s/g,
                        "",
                      )),
                      1 == this.userField[0].isMust && !this.form.userPhone)
                    )
                      throw "请输入手机号";
                    if (
                      1 == this.userField[1].isMust &&
                      !this.form.userRealname
                    )
                      throw "请输入姓名";
                    if (1 == this.userField[2].isMust && !this.form.userSex)
                      throw "请输入性别";
                    if (
                      1 == this.userField[3].isMust &&
                      !this.form.userBirthday
                    )
                      throw "请输入生日";
                    if (1 == this.userField[4].isMust && !this.form.userIdent)
                      throw "请输入身份证";
                    if (1 == this.userField[5].isMust && !this.form.userHeight)
                      throw "请输入身高";
                    if (1 == this.userField[6].isMust && !this.form.userWeight)
                      throw "请输入体重";
                    if (
                      1 == this.userField[7].isMust &&
                      !this.form.saleStaffUserid
                    )
                      throw "请选择会籍顾问";
                  } catch (t) {
                    return e.showToast({ icon: "none", title: t }), !1;
                  }
                  try {
                    if (
                      !/^(1[3-9]\d{9}|[0-9]{8}|[0-9]{9})$/.test(
                        this.form.userPhone,
                      )
                    )
                      throw "手机号格式不对";
                  } catch (t) {
                    return e.showToast({ icon: "none", title: t }), !1;
                  }
                  "" == this.form.userSex && (this.form.userSex = 2),
                    (this.form.userFaceurl = this.dbUrl);
                  var r = this.form;
                  (0, s.saveuser)(r).then(function (i) {
                    if (200 == i.code) {
                      var s = r.userId ? "编辑成功" : "会员添加成功";
                      r.userId && t.getlist({ userId: r.userId }),
                        t.getMenberList(),
                        e.showToast({ icon: "none", title: s, mask: !0 }),
                        setTimeout(function () {
                          e.navigateBack({ delta: 1 });
                        }, 1500);
                    } else e.showToast({ icon: "none", title: i.msg });
                  });
                },
                headleDelete: function () {
                  (this.delChecked = !0),
                    (this.$refs.delConfirmModal.show = !0);
                },
                handleCancelbtn: function () {
                  this.$refs.delConfirmModal.show = !1;
                },
                handleDeterminebtn: function () {
                  var t = this;
                  console.log(this.delChecked),
                    (this.$refs.delConfirmModal.show = !1);
                  var r = this.form.userId;
                  (0, s.delUser)({
                    delorderTag: this.delChecked,
                    userId: r,
                  }).then(function (i) {
                    200 == i.code
                      ? (t.getlist({ userId: r }),
                        e.showToast({
                          icon: "none",
                          title: "删除成功 ",
                          mask: !0,
                        }))
                      : e.showToast({ icon: "none", title: i.msg });
                  }),
                    setTimeout(function () {
                      e.switchTab({ url: "/pages/member/member" });
                    }, 1500);
                },
                headleSwitch: function (e) {
                  console.log(e);
                },
                headleClean: function () {
                  this.explainText = "";
                },
                preservation: function () {
                  var t = this,
                    r = this.form;
                  if ("" == this.explainText)
                    return (
                      e.showToast({
                        title: "请输入会员信息",
                        icon: "none",
                        mask: !0,
                      }),
                      !1
                    );
                  (0, s.adduserbatch)({ data: this.explainText }).then(
                    function (i) {
                      200 == i.code
                        ? (r.userId && t.getlist({ userId: r.userId }),
                          t.getMenberList(),
                          i.failCount > 0
                            ? ((t.errorStatus = !0),
                              (t.errorMsgList = i.failline))
                            : (e.showToast({
                                icon: "none",
                                title: "会员添加成功",
                                mask: !0,
                              }),
                              setTimeout(function () {
                                e.navigateBack({ delta: 1 });
                              }, 1500)))
                        : e.showToast({ icon: "none", title: i.msg });
                    },
                  );
                },
                searchInput: function (e) {
                  this.explainTextNum = this.explainText.split("\n").length;
                },
              },
              onLoad: function (e) {
                (this.userId = e.userId),
                  e.userId &&
                    ((this.userId = e.userId),
                    this.getlist({ userId: this.userId })),
                  this.getUserList();
              },
            };
          t.default = u;
        }).call(this, r("df3c").default);
      },
      8049: function (e, t, r) {
        "use strict";
        r.r(t);
        var i = r("5582"),
          s = r.n(i);
        for (var n in i)
          ["default"].indexOf(n) < 0 &&
            (function (e) {
              r.d(t, e, function () {
                return i[e];
              });
            })(n);
        t.default = s.a;
      },
      af6d: function (e, t, r) {
        "use strict";
        (function (e, t) {
          var i = r("47a9");
          r("86d2"), i(r("3240"));
          var s = i(r("1c09"));
          (e.__webpack_require_UNI_MP_PLUGIN__ = r), t(s.default);
        }).call(this, r("3223").default, r("df3c").createPage);
      },
      b45c: function (e, t, r) {},
      e7ec: function (e, t, r) {
        "use strict";
        r.d(t, "b", function () {
          return s;
        }),
          r.d(t, "c", function () {
            return n;
          }),
          r.d(t, "a", function () {
            return i;
          });
        var i = {
            uSwitch: function () {
              return r
                .e("uview-ui/components/u-switch/u-switch")
                .then(r.bind(null, "a048"));
            },
            uInput: function () {
              return Promise.all([
                r.e("common/vendor"),
                r.e("uview-ui/components/u-input/u-input"),
              ]).then(r.bind(null, "b5ea"));
            },
            uIcon: function () {
              return r
                .e("uview-ui/components/u-icon/u-icon")
                .then(r.bind(null, "81af"));
            },
            ffBottomLogo: function () {
              return r
                .e("components/ff-bottom-logo/ff-bottom-logo")
                .then(r.bind(null, "3111"));
            },
            uPicker: function () {
              return Promise.all([
                r.e("common/vendor"),
                r.e("uview-ui/components/u-picker/u-picker"),
              ]).then(r.bind(null, "46da"));
            },
            uPopup: function () {
              return r
                .e("uview-ui/components/u-popup/u-popup")
                .then(r.bind(null, "40dc"));
            },
          },
          s = function () {
            var e = this,
              t =
                (e.$createElement,
                e._self._c,
                0 != e.checked || e.avatar
                  ? null
                  : e.imgsrc("/static/imgs/portrait.png")),
              r =
                0 == e.checked && e.userId
                  ? e.imgsrc("/static/imgs/camera.png")
                  : null,
              i = 0 == e.checked ? e.userField.length : null,
              s = 0 == e.checked && i > 0 ? e.checkUserField(2) : null,
              n =
                0 == e.checked && i > 0 && s
                  ? e.checkUserField(2, "ismust")
                  : null,
              o = 0 == e.checked && i > 0 ? e.checkUserField(1) : null,
              u = 0 == e.checked && i > 0 ? e.checkUserField(3) : null,
              a =
                0 == e.checked && i > 0 && u
                  ? e.checkUserField(3, "ismust")
                  : null,
              l =
                0 == e.checked && i > 0 && u
                  ? e.imgsrc("/static/imgs/report_right_arrow.png")
                  : null,
              c = 0 == e.checked && i > 0 ? e.checkUserField(4) : null,
              h =
                0 == e.checked && i > 0 && c
                  ? e.checkUserField(4, "ismust")
                  : null,
              d =
                0 == e.checked && i > 0 && c && e.form.userBirthday
                  ? e.form.userBirthday.substring(0, 10)
                  : null,
              f =
                0 == e.checked && i > 0 && c
                  ? e.imgsrc("/static/imgs/report_right_arrow.png")
                  : null,
              m = 0 == e.checked && i > 0 ? e.checkUserField(5) : null,
              p =
                0 == e.checked && i > 0 && m
                  ? e.checkUserField(5, "ismust")
                  : null,
              g = 0 == e.checked && i > 0 ? e.checkUserField(6) : null,
              k =
                0 == e.checked && i > 0 && g
                  ? e.checkUserField(6, "ismust")
                  : null,
              v =
                0 == e.checked && i > 0 && g
                  ? e.imgsrc("/static/imgs/report_right_arrow.png")
                  : null,
              b = 0 == e.checked && i > 0 ? e.checkUserField(7) : null,
              I =
                0 == e.checked && i > 0 && b
                  ? e.checkUserField(7, "ismust")
                  : null,
              w =
                0 == e.checked && i > 0 && b
                  ? e.imgsrc("/static/imgs/report_right_arrow.png")
                  : null,
              T = 0 == e.checked && i > 0 ? e.checkUserField(8) : null,
              S =
                0 == e.checked && i > 0 && T
                  ? e.checkUserField(8, "ismust")
                  : null,
              U =
                0 == e.checked && i > 0 && T
                  ? e.imgsrc("/static/imgs/report_right_arrow.png")
                  : null,
              x =
                0 == e.checked && i > 0
                  ? e.imgsrc("/static/imgs/report_right_arrow.png")
                  : null,
              F =
                0 != e.checked
                  ? {
                      padding: "28rpx 26rpx",
                      color: "#7e7e7e",
                      "font-size": "28rpx",
                      height: "650rpx",
                    }
                  : null,
              M =
                e.personalTainerInfo && e.personalTainerInfo.hasOrderPay
                  ? e.imgsrc("/static/imgs/right.png")
                  : null;
            e._isMounted ||
              (e.e0 = function (t) {
                e.errorStatus = !1;
              }),
              (e.$mp.data = Object.assign(
                {},
                {
                  $root: {
                    m0: t,
                    m1: r,
                    g0: i,
                    m2: s,
                    m3: n,
                    m4: o,
                    m5: u,
                    m6: a,
                    m7: l,
                    m8: c,
                    m9: h,
                    g1: d,
                    m10: f,
                    m11: m,
                    m12: p,
                    m13: g,
                    m14: k,
                    m15: v,
                    m16: b,
                    m17: I,
                    m18: w,
                    m19: T,
                    m20: S,
                    m21: U,
                    m22: x,
                    a0: F,
                    m23: M,
                  },
                },
              ));
          },
          n = [];
      },
      fcb5: function (e, t, r) {
        "use strict";
        var i = r("b45c");
        r.n(i).a;
      },
    },
    [["af6d", "common/runtime", "common/vendor"]],
  ]);
